<?php

namespace App\Http\Controllers;

use App\Ai\Agents\SqlQueryAssistant;
use App\Http\Requests\ExecuteQueryRequest;
use App\Http\Requests\GenerateAiQueryRequest;
use App\Http\Requests\ValidateQueryRequest;
use App\Models\DatabaseConnection;
use App\Models\QueryRun;
use App\Models\SavedQuery;
use App\Services\AiSqlAssistantService;
use App\Services\AiMemoryProfileService;
use App\Services\AuditService;
use App\Services\QueryValidationService;
use App\Services\ReadOnlyQueryExecutor;
use App\Services\SchemaIntrospectionService;
use App\Services\SchemaPermissionService;
use App\Services\SqlDialectStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class QueryController extends Controller
{
    public function __construct(
        private readonly QueryValidationService $queryValidationService,
        private readonly ReadOnlyQueryExecutor $readOnlyQueryExecutor,
        private readonly SchemaPermissionService $schemaPermissionService,
        private readonly SchemaIntrospectionService $schemaIntrospectionService,
        private readonly AiSqlAssistantService $aiSqlAssistantService,
        private readonly AiMemoryProfileService $aiMemoryProfileService,
        private readonly SqlDialectStrategy $sqlDialectStrategy,
        private readonly AuditService $auditService,
    ) {}

    public function validate(ValidateQueryRequest $request): JsonResponse
    {
        $connection = DatabaseConnection::query()->findOrFail($request->integer('connection_id'));
        $dialect = $this->sqlDialectStrategy->resolveForDriver($connection->driver);
        $hasGlobalAccess = $request->user()?->hasPermission('connections.create') ?? false;
        $allowedTables = $this->schemaPermissionService->allowedTables($request->user(), $connection);

        if (! $hasGlobalAccess && $allowedTables === []) {
            return response()->json(['message' => 'No table access configured for this user.'], 403);
        }

        $validation = $this->queryValidationService->validate(
            $request->string('sql')->toString(),
            min((int) config('monitorsql.max_rows', 1000), $connection->max_rows),
            $hasGlobalAccess ? [] : $allowedTables,
            $dialect,
        );

        $this->auditService->record(
            action: 'query.validated',
            user: $request->user(),
            connection: $connection,
            sql: $request->string('sql')->toString(),
            status: $validation['is_valid'] ? 'success' : 'blocked',
            request: $request,
            metadata: ['errors' => $validation['errors']],
        );

        return response()->json($validation, $validation['is_valid'] ? 200 : 422);
    }

    public function execute(ExecuteQueryRequest $request): JsonResponse
    {
        $connection = DatabaseConnection::query()->findOrFail($request->integer('connection_id'));
        $dialect = $this->sqlDialectStrategy->resolveForDriver($connection->driver);
        $hasGlobalAccess = $request->user()?->hasPermission('connections.create') ?? false;
        $allowedTables = $this->schemaPermissionService->allowedTables($request->user(), $connection);

        if (! $hasGlobalAccess && $allowedTables === []) {
            return response()->json(['message' => 'No table access configured for this user.'], 403);
        }

        $validation = $this->queryValidationService->validate(
            $request->string('sql')->toString(),
            min((int) config('monitorsql.max_rows', 1000), $connection->max_rows),
            $hasGlobalAccess ? [] : $allowedTables,
            $dialect,
        );

        if (! $validation['is_valid']) {
            $this->auditService->record(
                action: 'query.blocked',
                user: $request->user(),
                connection: $connection,
                sql: $request->string('sql')->toString(),
                status: 'blocked',
                request: $request,
                metadata: ['errors' => $validation['errors']],
            );

            return response()->json([
                'message' => 'SQL validation failed.',
                'errors' => $validation['errors'],
            ], 422);
        }

        try {
            $result = $this->readOnlyQueryExecutor->execute(
                $connection,
                $validation['sql_with_limit'],
                $validation['limited'],
            );
        } catch (Throwable $throwable) {
            report($throwable);

            $this->auditService->record(
                action: 'query.failed',
                user: $request->user(),
                connection: $connection,
                sql: $validation['sql_with_limit'],
                status: 'failed',
                request: $request,
                metadata: ['reason' => 'sanitized_sql_error'],
            );

            return response()->json([
                'message' => 'The SQL engine returned a sanitized error response.',
            ], 422);
        }

        QueryRun::create([
            'user_id' => $request->user()?->id,
            'connection_id' => $connection->id,
            'sql' => $request->string('sql')->toString(),
            'normalized_sql' => $validation['normalized_sql'],
            'sql_hash' => $validation['sql_hash'],
            'status' => 'success',
            'duration_ms' => $result['meta']['duration_ms'],
            'rows_returned' => $result['meta']['row_count'],
            'is_ai_generated' => $request->boolean('is_ai_generated'),
            'meta' => [
                'columns' => $result['columns'],
                'tables' => $validation['tables'],
            ],
        ]);

        if ($request->user() !== null) {
            $this->aiMemoryProfileService->recordSuccessfulExecution(
                user: $request->user(),
                connectionId: $connection->id,
                sql: $validation['sql_with_limit'],
                tablesUsed: $validation['tables'],
            );
        }

        $this->auditService->record(
            action: 'query.executed',
            user: $request->user(),
            connection: $connection,
            sql: $validation['sql_with_limit'],
            status: 'success',
            request: $request,
            durationMs: $result['meta']['duration_ms'],
            rowsReturned: $result['meta']['row_count'],
            metadata: ['tables' => $validation['tables']],
        );

        return response()->json($result);
    }

    public function aiGenerate(GenerateAiQueryRequest $request): JsonResponse
    {
        $connection = DatabaseConnection::query()->findOrFail($request->integer('connection_id'));
        $dialect = $this->sqlDialectStrategy->resolveForDriver($connection->driver);
        $user = $request->user();
        $hasGlobalAccess = $request->user()?->hasPermission('connections.create') ?? false;
        $allowedTables = $this->schemaPermissionService->allowedTables($request->user(), $connection);
        $conversationId = $request->string('conversation_id')->toString();

        if (! $hasGlobalAccess && $allowedTables === []) {
            return response()->json(['message' => 'No table access configured for this user.'], 403);
        }

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($conversationId !== '' && ! $this->conversationBelongsToUser($conversationId, $user->id)) {
            return response()->json([
                'message' => 'The provided conversation_id is invalid for this user.',
            ], 422);
        }

        $effectiveAllowedTables = $hasGlobalAccess
            ? $this->resolveAdminAllowedTables($connection, $request->input('selected_tables', []))
            : $allowedTables;

        $selectedTables = collect($request->input('selected_tables', []))
            ->filter(fn (mixed $table): bool => is_string($table) && in_array($table, $effectiveAllowedTables, true))
            ->values()
            ->all();

        $aiResponse = $this->aiSqlAssistantService->generateStructuredQuery(
            user: $user,
            connection: $connection,
            question: $request->string('question')->toString(),
            allowedTables: $effectiveAllowedTables,
            selectedTables: $selectedTables,
            conversationId: $conversationId !== '' ? $conversationId : null,
        );

        $validation = $this->queryValidationService->validate(
            $aiResponse['sql'],
            min((int) config('monitorsql.max_rows', 1000), $connection->max_rows),
            $effectiveAllowedTables,
            $dialect,
        );

        if (! $validation['is_valid']) {
            $this->auditService->record(
                action: 'query.ai.blocked',
                user: $request->user(),
                connection: $connection,
                sql: $aiResponse['sql'],
                status: 'blocked',
                request: $request,
                metadata: ['errors' => $validation['errors']],
            );

            return response()->json([
                ...$aiResponse,
                'sql' => '',
                'confidence' => 'low',
                'message' => 'SQL validation failed: '.implode('; ', $validation['errors']),
                'errors' => $validation['errors'],
            ], 422);
        }

        $aiResponse['sql'] = $validation['sql_with_limit'];
        $aiResponse['tables_used'] = $validation['tables'];

        $this->auditService->record(
            action: 'query.ai.generated',
            user: $request->user(),
            connection: $connection,
            sql: $aiResponse['sql'],
            status: 'success',
            request: $request,
            metadata: ['question' => $request->string('question')->toString()],
        );

        return response()->json([
            ...$aiResponse,
            'requires_confirmation' => true,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $runs = QueryRun::query()
            ->where('user_id', $request->user()?->id)
            ->latest()
            ->paginate(15);

        return response()->json($runs);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:database_connections,id'],
            'name' => ['required', 'string', 'max:255'],
            'sql' => ['required', 'string'],
            'is_favorite' => ['sometimes', 'boolean'],
        ]);

        $savedQuery = SavedQuery::create([
            ...$data,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(['saved_query' => $savedQuery], 201);
    }

    public function aiSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:database_connections,id'],
            'sql' => ['required', 'string'],
            'result_sample' => ['required', 'array'],
        ]);

        $connection = DatabaseConnection::query()->findOrFail($data['connection_id']);

        $columns = Arr::get($data, 'result_sample.columns', []);
        $rows = Arr::get($data, 'result_sample.rows', []);
        $rowCount = Arr::get($data, 'result_sample.row_count', 0);
        $sql = $data['sql'];

        $columnNames = collect($columns)->pluck('name')->implode(', ');

        $sampleText = collect($rows)
            ->take(5)
            ->map(fn (array $row): string => json_encode($row, JSON_UNESCAPED_UNICODE))
            ->implode("\n");

        $prompt = <<<PROMPT
You are a data analyst. Summarize the following SQL query results in 2-4 clear sentences in the same language as the column names suggest (or Spanish by default).

SQL executed: {$sql}
Columns: {$columnNames}
Total rows: {$rowCount}
Sample rows (up to 5):
{$sampleText}

Provide a concise, business-friendly summary of what the data shows. Highlight key patterns, totals, or outliers. Do not mention the SQL itself.
PROMPT;

        try {
            $response = SqlQueryAssistant::make()->prompt(
                prompt: $prompt,
                provider: config('monitorsql.ai.provider', 'openai'),
                timeout: 30,
            );

            return response()->json([
                'summary' => (string) $response,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'summary' => $this->buildBasicSummary($columns, $rows, $rowCount),
            ]);
        }
    }

    public function favorites(Request $request): JsonResponse
    {
        $favorites = SavedQuery::query()
            ->where('user_id', $request->user()?->id)
            ->where('is_favorite', true)
            ->latest()
            ->get(['id', 'name', 'sql', 'connection_id', 'created_at']);

        return response()->json(['data' => $favorites]);
    }

    /**
     * @param  array<int, array{name: string, type: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function buildBasicSummary(array $columns, array $rows, int $rowCount): string
    {
        $columnCount = count($columns);
        $rowCountDisplay = $rowCount > 0 ? $rowCount : count($rows);

        return sprintf(
            'La consulta devolvió %d filas con %d columnas. No se pudo generar un resumen automático detallado.',
            $rowCountDisplay,
            $columnCount,
        );
    }

    /**
     * @param  array<int, mixed>  $selectedTables
     * @return array<int, string>
     */
    private function resolveAdminAllowedTables(DatabaseConnection $connection, array $selectedTables): array
    {
        $cleanSelected = collect($selectedTables)
            ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
            ->map(fn (string $table): string => trim($table))
            ->unique()
            ->values()
            ->all();

        if ($cleanSelected !== []) {
            return $cleanSelected;
        }

        $tables = $this->schemaIntrospectionService->listTables($connection);

        return collect($tables)
            ->pluck('name')
            ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function conversationBelongsToUser(string $conversationId, int|string|null $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return DB::table((string) config('ai.conversations.tables.conversations', 'agent_conversations'))
            ->where('id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }
}
