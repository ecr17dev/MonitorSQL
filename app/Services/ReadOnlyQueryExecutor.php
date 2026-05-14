<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;

class ReadOnlyQueryExecutor
{
    public function __construct(private readonly ConnectionService $connectionService) {}

    /**
     * @return array{
     *   columns: array<int, array{name: string, type: string}>,
     *   rows: array<int, array<string, mixed>>,
     *   meta: array{duration_ms: int, row_count: int, limited: bool}
     * }
     */
    public function execute(DatabaseConnection $connection, string $sql, bool $limited = true): array
    {
        $connectionName = $this->connectionService->registerRuntimeConnection($connection);

        $timeoutSeconds = max(1, $connection->query_timeout_seconds);
        $startedAt = microtime(true);

        if ($connection->driver === 'pgsql') {
            DB::connection($connectionName)->statement('SET statement_timeout TO '.($timeoutSeconds * 1000));
        }

        if ($connection->driver === 'mysql' || $connection->driver === 'mariadb') {
            DB::connection($connectionName)->statement('SET SESSION MAX_EXECUTION_TIME='.($timeoutSeconds * 1000));
        }

        $rows = DB::connection($connectionName)->select($sql);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        DB::purge($connectionName);

        $normalizedRows = collect($rows)
            ->map(fn (object $row): array => (array) $row)
            ->values()
            ->all();

        $firstRow = $normalizedRows[0] ?? [];
        $columns = collect(array_keys($firstRow))
            ->map(fn (string $column): array => [
                'name' => $column,
                'type' => get_debug_type($firstRow[$column] ?? null),
            ])
            ->values()
            ->all();

        return [
            'columns' => $columns,
            'rows' => $normalizedRows,
            'meta' => [
                'duration_ms' => $durationMs,
                'row_count' => count($normalizedRows),
                'limited' => $limited,
            ],
        ];
    }
}
