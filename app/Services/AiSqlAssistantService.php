<?php

namespace App\Services;

use App\Ai\Agents\SqlQueryAssistant;
use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class AiSqlAssistantService
{
    public function __construct(
        private readonly QueryValidationService $queryValidationService,
        private readonly SqlDialectStrategy $sqlDialectStrategy,
        private readonly SchemaContextBuilder $schemaContextBuilder,
        private readonly AiMemoryProfileService $aiMemoryProfileService,
    ) {}

    /**
     * @param  array<int, string>  $allowedTables
     * @param  array<int, string>  $selectedTables
     * @return array{
     *   sql: string,
     *   explanation: string,
     *   tables_used: array<int, string>,
     *   confidence: string,
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string},
     *   conversation_id: string|null,
     *   dialect: string,
     *   memory_applied: array{short_term: bool, long_term: bool},
     *   adaptation_note: string
     * }
     */
    public function generateStructuredQuery(
        User $user,
        DatabaseConnection $connection,
        string $question,
        array $allowedTables,
        array $selectedTables = [],
        ?string $conversationId = null,
    ): array {
        $candidateTables = $selectedTables !== [] ? $selectedTables : $allowedTables;
        $dialect = $this->sqlDialectStrategy->resolveForDriver($connection->driver);
        $memoryContext = $this->aiMemoryProfileService->promptContext($user, $connection->id);
        $schemaContext = $this->schemaContextBuilder->build($connection, $allowedTables, $selectedTables);

        if ($candidateTables === []) {
            return $this->withMetadata(
                response: $this->emptyResponse(),
                conversationId: null,
                dialect: $dialect,
                shortTermMemoryApplied: false,
                longTermMemoryApplied: $memoryContext['applied'],
            );
        }

        try {
            $agent = SqlQueryAssistant::make();
            $agent = $conversationId !== null
                ? $agent->continue($conversationId, $user)
                : $agent->forUser($user);

            $response = $agent->prompt(
                prompt: $this->buildPrompt(
                    question: $question,
                    dialect: $dialect,
                    allowedTables: $allowedTables,
                    selectedTables: $selectedTables,
                    schemaContext: $schemaContext['context'],
                    longTermMemoryContext: $memoryContext['context'],
                ),
                provider: $this->resolveProviderChain(),
                timeout: (int) config('monitorsql.ai.sql_timeout', 60),
            );

            $structured = method_exists($response, 'toArray')
                ? (array) $response->toArray()
                : (json_decode((string) $response, true) ?: []);

            $normalized = $this->normalizeStructuredResponse(
                structured: $structured,
                candidateTables: $schemaContext['tables_included'] !== [] ? $schemaContext['tables_included'] : $candidateTables,
            );

            $finalized = $this->finalizeQuery(
                response: $normalized,
                allowedTables: $allowedTables,
                dialect: $dialect,
            );

            if ($finalized['sql'] !== '') {
                $this->aiMemoryProfileService->recordGeneratedSuggestion(
                    user: $user,
                    connectionId: $connection->id,
                    question: $question,
                    sql: $finalized['sql'],
                    tablesUsed: $finalized['tables_used'],
                );
            }

            return $this->withMetadata(
                response: $finalized,
                conversationId: $response->conversationId,
                dialect: $dialect,
                shortTermMemoryApplied: $response->conversationId !== null,
                longTermMemoryApplied: $memoryContext['applied'],
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->withMetadata(
                response: $this->finalizeQuery(
                    response: $this->heuristicResponse($question, $candidateTables),
                    allowedTables: $allowedTables,
                    dialect: $dialect,
                ),
                conversationId: null,
                dialect: $dialect,
                shortTermMemoryApplied: false,
                longTermMemoryApplied: $memoryContext['applied'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $structured
     * @param  array<int, string>  $candidateTables
     * @return array{
     *   sql: string,
     *   explanation: string,
     *   tables_used: array<int, string>,
     *   confidence: string,
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string}
     * }
     */
    private function normalizeStructuredResponse(array $structured, array $candidateTables): array
    {
        $sql = rtrim(trim((string) Arr::get($structured, 'sql', '')), ';');
        $sql = trim($sql);
        $explanation = trim((string) Arr::get($structured, 'explanation', 'Generated query based on your request.'));
        $confidence = Str::lower((string) Arr::get($structured, 'confidence', 'medium'));

        if (! in_array($confidence, ['low', 'medium', 'high'], true)) {
            $confidence = 'medium';
        }

        $tablesUsed = collect(Arr::get($structured, 'tables_used', []))
            ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
            ->values()
            ->all();

        $visualization = [
            'type' => (string) Arr::get($structured, 'chart_type', Arr::get($structured, 'suggested_visualization.type', 'table')),
            'x_axis' => $this->nullableString(Arr::get($structured, 'chart_x_axis', Arr::get($structured, 'suggested_visualization.x_axis'))),
            'y_axis' => $this->nullableString(Arr::get($structured, 'chart_y_axis', Arr::get($structured, 'suggested_visualization.y_axis'))),
            'reason' => (string) Arr::get($structured, 'chart_reason', Arr::get($structured, 'suggested_visualization.reason', 'Tabular output is the safest default.')),
        ];

        $result = [
            'sql' => $sql,
            'explanation' => $explanation === '' ? 'Generated query based on your request.' : $explanation,
            'tables_used' => $tablesUsed,
            'confidence' => $confidence,
            'suggested_visualization' => $visualization,
        ];

        if ($result['sql'] === '') {
            return $this->heuristicResponse('fallback', $candidateTables);
        }

        return $result;
    }

    /**
     * @param  array{
     *   sql: string,
     *   explanation: string,
     *   tables_used: array<int, string>,
     *   confidence: string,
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string}
     * }  $response
     * @param  array<int, string>  $allowedTables
     * @return array{
     *   sql: string,
     *   explanation: string,
     *   tables_used: array<int, string>,
     *   confidence: string,
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string}
     * }
     */
    private function finalizeQuery(array $response, array $allowedTables, string $dialect): array
    {
        $validation = $this->queryValidationService->validate(
            $response['sql'],
            (int) config('monitorsql.max_rows', 1000),
            $allowedTables,
            $dialect,
        );

        $response['sql'] = $validation['sql_with_limit'];
        $response['tables_used'] = $validation['tables'];

        if (! $validation['is_valid']) {
            $response['confidence'] = 'low';
            $response['explanation'] = 'The generated SQL required additional validation. Please review it before execution.';
        }

        return $response;
    }

    /**
     * @param  array<int, string>  $allowedTables
     * @param  array<int, string>  $selectedTables
     */
    private function buildPrompt(
        string $question,
        string $dialect,
        array $allowedTables,
        array $selectedTables,
        string $schemaContext,
        string $longTermMemoryContext,
    ): string {
        $tablesContext = $this->formatTableList($allowedTables);
        $selectedContext = $selectedTables === [] ? 'none' : $this->formatTableList($selectedTables);
        $dialectRules = $this->sqlDialectStrategy->promptRules($dialect);

        return <<<PROMPT
User question:
{$question}

Dialect rules:
{$dialectRules}

Allowed tables (use fuzzy matching for Spanish/English terms):
{$tablesContext}

Selected tables (preferred scope, or 'none' for all):
{$selectedContext}

Schema context (tables, columns, types):
{$schemaContext}

Long-term memory profile context:
{$longTermMemoryContext}

Return a strictly valid structured response.
If a term doesn't exactly match a table, infer the closest allowed match.
Use read-only analytical patterns (CTEs, windows, deduplication, cohorts, funnels) only when supported by the active dialect.
PROMPT;
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function formatTableList(array $tables): string
    {
        if ($tables === []) {
            return '(empty)';
        }

        return collect($tables)
            ->map(fn (string $table): string => sprintf('  - %s', $table))
            ->implode("\n");
    }

    /**
     * @return array<string, string|null>|string
     */
    private function resolveProviderChain(): array|string
    {
        $primary = (string) config('monitorsql.ai.provider', 'openai');
        $primaryModel = $this->nullableString(config('monitorsql.ai.model'));
        $fallback = $this->nullableString(config('monitorsql.ai.fallback_provider'));
        $fallbackModel = $this->nullableString(config('monitorsql.ai.fallback_model'));

        if ($fallback === null || $fallback === '' || $fallback === $primary) {
            return [$primary => $primaryModel];
        }

        return [
            $primary => $primaryModel,
            $fallback => $fallbackModel,
        ];
    }

    /**
     * @param  array<int, string>  $candidateTables
     * @return array{
     *   sql: string,
     *   explanation: string,
     *   tables_used: array<int, string>,
     *   confidence: string,
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string}
     * }
     */
    private function heuristicResponse(string $question, array $candidateTables): array
    {
        $table = $candidateTables[0] ?? null;

        if ($table === null) {
            return $this->emptyResponse();
        }

        $normalizedQuestion = Str::lower(trim($question));

        if (Str::contains($normalizedQuestion, ['count', 'cuánt', 'cuant', 'total'])) {
            return [
                'sql' => sprintf('SELECT COUNT(*) AS total FROM %s', $table),
                'explanation' => 'Counts the total records from the selected table.',
                'tables_used' => [$table],
                'confidence' => 'low',
                'suggested_visualization' => [
                    'type' => 'kpi',
                    'x_axis' => null,
                    'y_axis' => 'total',
                    'reason' => 'A single aggregate value is returned.',
                ],
            ];
        }

        return [
            'sql' => sprintf('SELECT * FROM %s', $table),
            'explanation' => 'Returns rows from the selected table with read-only safety controls.',
            'tables_used' => [$table],
            'confidence' => 'low',
            'suggested_visualization' => [
                'type' => 'table',
                'x_axis' => null,
                'y_axis' => null,
                'reason' => 'Detailed row-level dataset.',
            ],
        ];
    }

    /**
     * @return array{
     *   sql: string,
     *   explanation: string,
     *   tables_used: array<int, string>,
     *   confidence: string,
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string}
     * }
     */
    private function emptyResponse(): array
    {
        return [
            'sql' => '',
            'explanation' => 'No authorized table was available for this request.',
            'tables_used' => [],
            'confidence' => 'low',
            'suggested_visualization' => [
                'type' => 'table',
                'x_axis' => null,
                'y_axis' => null,
                'reason' => 'No table was available for SQL generation.',
            ],
        ];
    }

    /**
     * @param  array{
     *   sql: string,
     *   explanation: string,
     *   tables_used: array<int, string>,
     *   confidence: string,
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string}
     * }  $response
     * @return array{
     *   sql: string,
     *   explanation: string,
     *   tables_used: array<int, string>,
     *   confidence: string,
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string},
     *   conversation_id: string|null,
     *   dialect: string,
     *   memory_applied: array{short_term: bool, long_term: bool},
     *   adaptation_note: string
     * }
     */
    private function withMetadata(
        array $response,
        ?string $conversationId,
        string $dialect,
        bool $shortTermMemoryApplied,
        bool $longTermMemoryApplied,
    ): array {
        return [
            ...$response,
            'conversation_id' => $conversationId,
            'dialect' => $dialect,
            'memory_applied' => [
                'short_term' => $shortTermMemoryApplied,
                'long_term' => $longTermMemoryApplied,
            ],
            'adaptation_note' => $this->sqlDialectStrategy->adaptationNote($dialect),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
