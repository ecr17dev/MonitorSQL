<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SchemaIntrospectionService
{
    public function __construct(private readonly ConnectionService $connectionService) {}

    /**
     * @return array<int, array{name: string}>
     */
    public function listSchemas(DatabaseConnection $connection): array
    {
        $connectionName = $this->connectionService->registerRuntimeConnection($connection);

        if ($connection->driver === 'pgsql') {
            $rows = DB::connection($connectionName)->select("select schema_name as name from information_schema.schemata where schema_name not in ('pg_catalog', 'information_schema') order by schema_name");
        } else {
            $rows = DB::connection($connectionName)->select('select database() as name');
        }

        DB::purge($connectionName);

        return collect($rows)
            ->map(fn (object $row): array => ['name' => (string) $row->name])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, schema: string|null}>
     */
    public function listTables(DatabaseConnection $connection): array
    {
        $connectionName = $this->connectionService->registerRuntimeConnection($connection);

        if ($connection->driver === 'pgsql') {
            $rows = DB::connection($connectionName)->select(
                "select table_schema as schema, table_name as name from information_schema.tables where table_type = 'BASE TABLE' and table_schema not in ('pg_catalog', 'information_schema') order by table_schema, table_name"
            );
        } else {
            $rows = DB::connection($connectionName)->select('select table_name as name, table_schema as schema from information_schema.tables where table_schema = database() order by table_name');
        }

        DB::purge($connectionName);

        return collect($rows)
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'schema' => isset($row->schema) ? (string) $row->schema : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, type: string, nullable: bool}>
     */
    public function describeTable(DatabaseConnection $connection, string $qualifiedTable): array
    {
        [$schema, $table] = $this->parseQualifiedTableIdentifier($qualifiedTable);
        $connectionName = $this->connectionService->registerRuntimeConnection($connection);

        if ($connection->driver === 'pgsql') {
            $query = DB::connection($connectionName)
                ->table('information_schema.columns')
                ->selectRaw('column_name as name, data_type as type, is_nullable')
                ->where('table_name', $table)
                ->orderBy('ordinal_position');

            if ($schema !== null) {
                $query->where('table_schema', $schema);
            }

            $rows = $query->get();
        } else {
            $query = DB::connection($connectionName)
                ->table('information_schema.columns')
                ->selectRaw('column_name as name, data_type as type, is_nullable')
                ->whereRaw('table_schema = database()')
                ->where('table_name', $table)
                ->orderBy('ordinal_position');

            $rows = $query->get();
        }

        DB::purge($connectionName);

        return collect($rows)
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'nullable' => (($row->is_nullable ?? 'NO') === 'YES'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{column: string, referenced_table: string, referenced_column: string}>
     */
    public function describeForeignKeys(DatabaseConnection $connection, string $qualifiedTable): array
    {
        [$schema, $table] = $this->parseQualifiedTableIdentifier($qualifiedTable);
        $connectionName = $this->connectionService->registerRuntimeConnection($connection);

        if ($connection->driver === 'pgsql') {
            $query = DB::connection($connectionName)
                ->table('information_schema.table_constraints as tc')
                ->selectRaw('kcu.column_name as column_name, ccu.table_name as referenced_table_name, ccu.column_name as referenced_column_name')
                ->join('information_schema.key_column_usage as kcu', fn ($join) => $join->on('tc.constraint_schema', '=', 'kcu.constraint_schema')->on('tc.constraint_name', '=', 'kcu.constraint_name'))
                ->join('information_schema.referential_constraints as rc', fn ($join) => $join->on('tc.constraint_schema', '=', 'rc.constraint_schema')->on('tc.constraint_name', '=', 'rc.constraint_name'))
                ->join('information_schema.constraint_column_usage as ccu', fn ($join) => $join->on('rc.unique_constraint_schema', '=', 'ccu.constraint_schema')->on('rc.unique_constraint_name', '=', 'ccu.constraint_name'))
                ->where('tc.table_name', $table)
                ->where('tc.constraint_type', 'FOREIGN KEY')
                ->orderBy('kcu.ordinal_position');

            if ($schema !== null) {
                $query->where('tc.table_schema', $schema);
            }

            $rows = $query->get();
        } else {
            $query = DB::connection($connectionName)
                ->table('information_schema.key_column_usage as kcu')
                ->selectRaw('kcu.column_name, kcu.referenced_table_name, kcu.referenced_column_name')
                ->whereRaw('kcu.table_schema = database()')
                ->where('kcu.table_name', $table)
                ->whereNotNull('kcu.referenced_table_name')
                ->orderBy('kcu.ordinal_position');

            $rows = $query->get();
        }

        DB::purge($connectionName);

        return collect($rows)
            ->map(fn (object $row): array => [
                'column' => (string) $row->column_name,
                'referenced_table' => (string) $row->referenced_table_name,
                'referenced_column' => (string) $row->referenced_column_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function previewTable(DatabaseConnection $connection, string $qualifiedTable, int $limit): array
    {
        $limit = max(1, min($limit, (int) config('monitorsql.max_rows', 1000)));
        $connectionName = $this->connectionService->registerRuntimeConnection($connection);

        $tableReference = $this->quotedQualifiedIdentifier($connection, $qualifiedTable);

        $rows = DB::connection($connectionName)->select(sprintf('SELECT * FROM %s LIMIT %d', $tableReference, $limit));

        DB::purge($connectionName);

        return collect($rows)
            ->map(fn (object $row): array => (array) $row)
            ->values()
            ->all();
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    public function parseQualifiedTableIdentifier(string $qualifiedTable): array
    {
        $parts = array_values(array_filter(explode('.', trim($qualifiedTable)), fn (string $part): bool => $part !== ''));

        if (count($parts) === 0 || count($parts) > 2) {
            throw new InvalidArgumentException('Invalid table identifier.');
        }

        if (count($parts) === 1) {
            $table = Arr::first($parts);
            $this->assertValidIdentifier((string) $table);

            return [null, (string) $table];
        }

        $schema = Arr::first($parts);
        $table = Arr::last($parts);

        $this->assertValidIdentifier((string) $schema);
        $this->assertValidIdentifier((string) $table);

        return [(string) $schema, (string) $table];
    }

    private function assertValidIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-zA-Z0-9_]+$/', $identifier) !== 1) {
            throw new InvalidArgumentException('Invalid SQL identifier.');
        }
    }

    private function quotedQualifiedIdentifier(DatabaseConnection $connection, string $qualifiedTable): string
    {
        [$schema, $table] = $this->parseQualifiedTableIdentifier($qualifiedTable);

        $quote = $connection->driver === 'pgsql' ? '"' : '`';
        $quoteIdentifier = fn (string $identifier): string => $quote.$identifier.$quote;

        if ($schema === null) {
            return $quoteIdentifier($table);
        }

        return $quoteIdentifier($schema).'.'.$quoteIdentifier($table);
    }
}
