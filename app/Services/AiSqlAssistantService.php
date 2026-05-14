<?php

namespace App\Services;

use App\Ai\Agents\SqlQueryAssistant;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class AiSqlAssistantService
{
    public function __construct(private readonly QueryValidationService $queryValidationService) {}

    /**
     * @param  array<int, string>  $allowedTables
     * @param  array<int, string>  $selectedTables
     * @return array{
     *   sql: string,
     *   explanation: string,
     *   tables_used: array<int, string>,
     *   confidence: string,
     *   suggested_visualization: array{type: string, x_axis: string|null, y_axis: string|null, reason: string}
     * }
     */
    public function generateStructuredQuery(string $question, array $allowedTables, array $selectedTables = []): array
    {
        $candidateTables = $selectedTables !== [] ? $selectedTables : $allowedTables;

        if ($candidateTables === []) {
            return $this->emptyResponse();
        }

        try {
            $response = SqlQueryAssistant::make()->prompt(
                prompt: $this->buildPrompt($question, $allowedTables, $selectedTables),
                provider: $this->resolveProviderChain(),
                timeout: (int) config('monitorsql.ai.sql_timeout', 60),
            );

            $structured = method_exists($response, 'toArray')
                ? (array) $response->toArray()
                : (json_decode((string) $response, true) ?: []);

            $normalized = $this->normalizeStructuredResponse($structured, $candidateTables);

            return $this->finalizeQuery($normalized, $allowedTables);
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->finalizeQuery(
                $this->heuristicResponse($question, $candidateTables),
                $allowedTables,
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
    private function finalizeQuery(array $response, array $allowedTables): array
    {
        $validation = $this->queryValidationService->validate(
            $response['sql'],
            (int) config('monitorsql.max_rows', 1000),
            $allowedTables,
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
    private function buildPrompt(string $question, array $allowedTables, array $selectedTables): string
    {
        $tablesContext = $this->formatTableList($allowedTables);
        $selectedContext = $selectedTables === [] ? 'none' : $this->formatTableList($selectedTables);

        return <<<PROMPT
User question:
{$question}

Allowed tables (use fuzzy matching for Spanish/English terms):
{$tablesContext}

Selected tables (preferred scope, or 'none' for all):
{$selectedContext}

Return a strictly valid structured response. If the user's term doesn't exactly match a table name, use your reasoning to find the closest match.
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
            ->map(fn (string $table): string => sprintf('  %s → %s', $table, $this->describeTable($table)))
            ->implode("\n");
    }

    private function describeTable(string $table): string
    {
        $descriptions = [
            'contacts' => 'contactos, información de contacto',
            'users' => 'usuarios del sistema',
            'properties' => 'propiedades inmobiliarias',
            'property_images' => 'imágenes/fotos de propiedades',
            'property_market_data' => 'datos de mercado de propiedades',
            'property_saved' => 'propiedades guardadas/favoritas',
            'pending_offers' => 'ofertas pendientes',
            'offer_history' => 'historial de ofertas',
            'negotiations' => 'negociaciones',
            'buyer_preferences' => 'preferencias de compradores',
            'buyer_notification_preferences' => 'preferencias de notificación de compradores',
            'cms_contents' => 'contenido del CMS',
            'activity_logs' => 'registros de actividad/logs del sistema',
            'ai_agent_settings' => 'configuración de agentes IA',
            'sessions' => 'sesiones de usuario',
            'cache' => 'datos de caché',
            'cache_locks' => 'bloqueos de caché',
            'failed_jobs' => 'trabajos fallidos',
            'job_batches' => 'lotes de trabajos',
            'jobs' => 'cola de trabajos',
            'migrations' => 'migraciones de base de datos',
            'password_reset_tokens' => 'tokens de restablecimiento de contraseña',
            'personal_access_tokens' => 'tokens de acceso personal',
        ];

        return $descriptions[$table] ?? 'datos de '.$table;
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
