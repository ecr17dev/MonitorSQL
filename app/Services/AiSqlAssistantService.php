<?php

namespace App\Services;

use App\Ai\Agents\SqlQueryAssistant;
use App\Ai\Agents\TableSelectorAgent;
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
     *   adaptation_note: string,
     *   warnings: array<int, string>
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
        $dialect = $this->sqlDialectStrategy->resolveForDriver($connection->driver);

        $memoryContext = $this->resolveMemoryContext($user, $connection->id);

        // Step 1 — Table Selection via AI
        $tableSelection = $this->selectTables(
            user: $user,
            question: $question,
            allowedTables: $allowedTables,
            selectedTables: $selectedTables,
            memoryContext: $memoryContext['context'],
        );

        $confirmedTables = $tableSelection['tables'];
        $selectionReason = $tableSelection['reason'];
        $selectionConfidence = $tableSelection['confidence'];
        $notFound = $tableSelection['not_found'];

        if ($confirmedTables === [] || $notFound) {
            return $this->withMetadata(
                response: [
                    'sql' => '',
                    'explanation' => $selectionReason !== ''
                        ? $selectionReason
                        : 'No se encontró una tabla que coincida con tu pregunta. Las tablas disponibles son: '.collect($allowedTables)->implode(', ').'.',
                    'tables_used' => [],
                    'confidence' => 'low',
                    'suggested_visualization' => [
                        'type' => 'table',
                        'x_axis' => null,
                        'y_axis' => null,
                        'reason' => 'No matching tables were found.',
                    ],
                    'warnings' => $tableSelection['warnings'],
                ],
                conversationId: null,
                dialect: $dialect,
                shortTermMemoryApplied: false,
                longTermMemoryApplied: $memoryContext['applied'],
            );
        }

        // Step 2 — SQL Generation with confirmed tables only
        try {
            $schemaContext = $this->schemaContextBuilder->build($connection, $allowedTables, $confirmedTables);
        } catch (Throwable) {
            $schemaContext = ['context' => '(schema introspection failed)', 'tables_included' => $confirmedTables, 'truncated' => false];
        }

        $warnings = $tableSelection['warnings'];

        if ($schemaContext['truncated'] ?? false) {
            $warnings[] = 'El contexto del schema fue truncado — algunas columnas pueden no estar disponibles para la consulta.';
        }

        $allSelectableConfirmed = collect($confirmedTables)
            ->every(fn (string $table): bool => in_array($table, $allowedTables, true));

        if (! $allSelectableConfirmed) {
            return $this->withMetadata(
                response: [
                    'sql' => '',
                    'explanation' => 'Algunas tablas seleccionadas no están autorizadas. Las tablas disponibles son: '.collect($allowedTables)->implode(', ').'.',
                    'tables_used' => [],
                    'confidence' => 'low',
                    'suggested_visualization' => [
                        'type' => 'table',
                        'x_axis' => null,
                        'y_axis' => null,
                        'reason' => 'Table selection failed validation.',
                    ],
                    'warnings' => $warnings,
                ],
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
                    confirmedTables: $confirmedTables,
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
                candidateTables: $schemaContext['tables_included'] !== [] ? $schemaContext['tables_included'] : $confirmedTables,
            );

            $finalized = $this->finalizeQuery(
                response: $normalized,
                allowedTables: $allowedTables,
                dialect: $dialect,
            );

            $responseWarnings = Arr::get($structured, 'warnings', []);
            if (is_array($responseWarnings)) {
                $warnings = [...$warnings, ...$responseWarnings];
            }

            $finalized['warnings'] = $warnings;

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
                    response: $this->heuristicResponse($question, $confirmedTables),
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
     * Step 1 — Use the TableSelectorAgent to pick the correct tables.
     *
     * @param  array<int, string>  $allowedTables
     * @param  array<int, string>  $selectedTables
     * @return array{tables: array<int, string>, reason: string, confidence: string, not_found: bool, warnings: array<int, string>}
     */
    private function selectTables(
        User $user,
        string $question,
        array $allowedTables,
        array $selectedTables,
        string $memoryContext,
    ): array {
        // If user has already manually selected tables, skip AI selection
        if ($selectedTables !== []) {
            return [
                'tables' => $selectedTables,
                'reason' => 'Tablas seleccionadas manualmente por el usuario.',
                'confidence' => 'high',
                'not_found' => false,
                'warnings' => [],
            ];
        }

        if ($allowedTables === []) {
            return [
                'tables' => [],
                'reason' => 'No hay tablas autorizadas para esta conexión.',
                'confidence' => 'low',
                'not_found' => true,
                'warnings' => [],
            ];
        }

        try {
            $agent = TableSelectorAgent::make()->forUser($user);

            $selectionPrompt = $this->buildTableSelectionPrompt(
                question: $question,
                allowedTables: $allowedTables,
                memoryContext: $memoryContext,
            );

            $response = $agent->prompt(
                prompt: $selectionPrompt,
                provider: $this->resolveProviderChain(),
                timeout: 30,
            );

            $structured = method_exists($response, 'toArray')
                ? (array) $response->toArray()
                : (json_decode((string) $response, true) ?: []);

            $tables = collect(Arr::get($structured, 'tables', []))
                ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
                ->values()
                ->all();

            $reason = trim((string) Arr::get($structured, 'reason', ''));

            if ($tables === []) {
                $reason = $reason !== ''
                    ? $reason
                    : 'No se pudo identificar una tabla que coincida con tu pregunta.';
            }

            return [
                'tables' => array_values(array_intersect($tables, $allowedTables)),
                'reason' => $reason,
                'confidence' => Str::lower((string) Arr::get($structured, 'confidence', 'medium')),
                'not_found' => (bool) Arr::get($structured, 'not_found', false),
                'warnings' => [],
            ];
        } catch (Throwable) {
            // Fallback: use keyword-based selection
            return $this->selectTablesHeuristically($question, $allowedTables);
        }
    }

    /**
     * @param  array<int, string>  $allowedTables
     * @return array{tables: array<int, string>, reason: string, confidence: string, not_found: bool, warnings: array<int, string>}
     */
    private function selectTablesHeuristically(string $question, array $allowedTables): array
    {
        $normalizedQuestion = Str::lower(trim($question));
        $matched = [];

        $termTableMap = [
            'contacto' => 'contacts',
            'contacts' => 'contacts',
            'usuario' => 'users',
            'users' => 'users',
            'propiedad' => 'properties',
            'properties' => 'properties',
            'imagen' => 'property_images',
            'foto' => 'property_images',
            'images' => 'property_images',
            'oferta' => ['pending_offers', 'offer_history'],
            'offer' => ['pending_offers', 'offer_history'],
            'negociacion' => 'negotiations',
            'negotiation' => 'negotiations',
            'notificacion' => 'buyer_notification_preferences',
            'notification' => 'buyer_notification_preferences',
            'guardado' => 'property_saved',
            'favorito' => 'property_saved',
            'saved' => 'property_saved',
            'contenido' => 'cms_contents',
            'content' => 'cms_contents',
            'cms' => 'cms_contents',
            'actividad' => 'activity_logs',
            'activity' => 'activity_logs',
            'historial' => ['offer_history', 'activity_logs'],
            'history' => ['offer_history', 'activity_logs'],
        ];

        foreach ($termTableMap as $term => $candidates) {
            if (str_contains($normalizedQuestion, $term)) {
                foreach ((array) $candidates as $candidate) {
                    if (in_array($candidate, $allowedTables, true) && ! in_array($candidate, $matched, true)) {
                        $matched[] = $candidate;
                    }
                }
            }
        }

        if ($matched === [] && $allowedTables !== []) {
            $matched = [$allowedTables[0]];
        }

        return [
            'tables' => $matched,
            'reason' => $matched !== [] ? 'Selección heurística de tablas basada en la pregunta.' : '',
            'confidence' => 'low',
            'not_found' => $matched === [],
            'warnings' => [],
        ];
    }

    /**
     * @param  array<int, string>  $allowedTables
     */
    private function buildTableSelectionPrompt(
        string $question,
        array $allowedTables,
        string $memoryContext,
    ): string {
        $tablesContext = $this->formatTableList($allowedTables);

        return <<<PROMPT
User question:
{$question}

ALLOWED TABLES (ONLY these tables exist — DO NOT reference any other table):
{$tablesContext}

Long-term memory profile context:
{$memoryContext}

Select the correct table(s) for this question. Return structured output only.
PROMPT;
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
        if ($response['sql'] === '') {
            return $response;
        }

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
     * @param  array<int, string>  $confirmedTables
     */
    private function buildPrompt(
        string $question,
        string $dialect,
        array $allowedTables,
        array $confirmedTables,
        string $schemaContext,
        string $longTermMemoryContext,
    ): string {
        $confirmedContext = $this->formatTableList($confirmedTables);
        $dialectRules = $this->sqlDialectStrategy->promptRules($dialect);

        return <<<PROMPT
User question:
{$question}

Dialect rules:
{$dialectRules}

CONFIRMED TABLES (you may ONLY use these tables — all have been verified to exist):
{$confirmedContext}

Schema context (tables, columns, types, foreign keys):
{$schemaContext}

Long-term memory profile context:
{$longTermMemoryContext}

IMPORTANT: The tables above were independently verified. Do NOT add, invent, or reference any other table.
If a column name ends with _id (e.g., quote_id, lead_id), this does NOT mean a corresponding table exists. Only tables listed under "CONFIRMED TABLES" above are real.
Foreign keys marked "[WARNING: NOT available for JOIN]" must not be used in JOIN conditions.

Return strictly valid structured output with all required fields.
If you cannot generate a safe query with the confirmed tables, set sql="" and explain why.
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
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string},
     *   warnings?: array<int, string>
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
     *   adaptation_note: string,
     *   warnings: array<int, string>
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
            'warnings' => $response['warnings'] ?? [],
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

    /**
     * @return array{context: string, applied: bool}
     */
    private function resolveMemoryContext(User $user, int $connectionId): array
    {
        try {
            return $this->aiMemoryProfileService->promptContext($user, $connectionId);
        } catch (Throwable) {
            return ['applied' => false, 'context' => ''];
        }
    }
}
