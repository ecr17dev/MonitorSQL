<?php

namespace App\Services;

use App\Models\DatabaseConnection;

class SchemaContextBuilder
{
    public function __construct(
        private readonly SchemaIntrospectionService $schemaIntrospectionService,
        private readonly SqlDialectStrategy $sqlDialectStrategy,
    ) {}

    /**
     * @param  array<int, string>  $allowedTables
     * @param  array<int, string>  $selectedTables
     * @return array{context: string, tables_included: array<int, string>, truncated: bool}
     */
    public function build(DatabaseConnection $connection, array $allowedTables, array $selectedTables = []): array
    {
        $maxSchemaTokens = (int) config('monitorsql.ai.max_schema_tokens', 12000);
        $maxChars = max(2000, $maxSchemaTokens * 4);
        $dialect = $this->sqlDialectStrategy->resolveForDriver($connection->driver);

        try {
            $tableList = $this->schemaIntrospectionService->listTables($connection);
        } catch (\Throwable) {
            $tableList = [];
        }

        $tableByName = collect($tableList)
            ->filter(fn (array $table): bool => ! empty($table['name']))
            ->keyBy(fn (array $table): string => (string) $table['name']);

        $orderedCandidates = collect([...$selectedTables, ...$allowedTables])
            ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
            ->unique()
            ->values();

        $totalTables = $orderedCandidates->count();
        $includedCount = 0;
        $skippedTables = [];

        $header = [
            sprintf('Active SQL dialect: %s', $dialect),
            'Use only these authorized tables and columns.',
            '',
        ];

        $context = implode("\n", $header);
        $tablesIncluded = [];
        $truncated = false;

        foreach ($orderedCandidates as $tableName) {
            $tableMeta = $tableByName->get($tableName);

            if (! is_array($tableMeta)) {
                $skippedTables[] = (string) $tableName;

                continue;
            }

            $qualifiedName = $this->qualifiedTableName($tableMeta, $dialect);

            try {
                $columns = $this->schemaIntrospectionService->describeTable($connection, $qualifiedName);
                $foreignKeys = $this->schemaIntrospectionService->describeForeignKeys($connection, $qualifiedName);
            } catch (\Throwable) {
                $skippedTables[] = (string) $tableName;

                continue;
            }

            $columnLines = collect($columns)
                ->map(function (array $column): string {
                    $nullable = ($column['nullable'] ?? false) ? 'nullable' : 'not null';

                    return sprintf(
                        '  - %s (%s, %s)',
                        (string) ($column['name'] ?? 'unknown_column'),
                        (string) ($column['type'] ?? 'unknown_type'),
                        $nullable,
                    );
                })
                ->implode("\n");

            $allowedFkLines = collect($foreignKeys)
                ->filter(function (array $fk) use ($allowedTables): bool {
                    $refTable = $fk['referenced_table'];

                    return in_array($refTable, $allowedTables, true);
                })
                ->map(function (array $fk): string {
                    return sprintf(
                        '  - %s -> %s(%s)',
                        (string) $fk['column'],
                        (string) $fk['referenced_table'],
                        (string) $fk['referenced_column'],
                    );
                })
                ->implode("\n");

            $restrictedFkLines = collect($foreignKeys)
                ->filter(function (array $fk) use ($allowedTables): bool {
                    $refTable = $fk['referenced_table'];

                    return ! in_array($refTable, $allowedTables, true);
                })
                ->map(function (array $fk): string {
                    return sprintf(
                        '  - %s -> %s(%s) [WARNING: referenced table "%s" is NOT available for JOIN - do not use it]',
                        (string) $fk['column'],
                        (string) $fk['referenced_table'],
                        (string) $fk['referenced_column'],
                        (string) $fk['referenced_table'],
                    );
                })
                ->implode("\n");

            $fkSection = '';

            if ($allowedFkLines !== '') {
                $fkSection .= sprintf("Foreign keys (to allowed tables):\n%s\n", $allowedFkLines);
            }

            if ($restrictedFkLines !== '') {
                $fkSection .= sprintf("\nForeign keys (to RESTRICTED tables — DO NOT JOIN):\n%s\n", $restrictedFkLines);
            }

            $section = sprintf("Table: %s\n%s\n", $qualifiedName, $columnLines);

            if ($fkSection !== '') {
                $section .= $fkSection."\n";
            }

            $section .= "\n";

            if (strlen($context.$section) > $maxChars) {
                $truncated = true;
                // Collect remaining table names for the truncation notice
                $remaining = $orderedCandidates->slice($includedCount)->values();
                foreach ($remaining as $remainingTable) {
                    $skippedTables[] = (string) $remainingTable;
                }
                break;
            }

            $context .= $section;
            $tablesIncluded[] = (string) $tableName;
            $includedCount++;
        }

        if ($truncated && $skippedTables !== []) {
            $uniqueSkipped = array_values(array_unique($skippedTables));
            $context .= sprintf(
                "\nSCHEMA TRUNCATION NOTICE: %d of %d tables were included due to size limits.\n",
                $includedCount,
                $totalTables,
            );
            $context .= 'The following tables exist but their schema was omitted: '.implode(', ', $uniqueSkipped)."\n";
            $context .= "These tables still exist and can be used in queries, but only columns visible above are verified.\n";
        }

        if ($tablesIncluded === []) {
            $fallbackList = collect($allowedTables)
                ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
                ->unique()
                ->values()
                ->implode(', ');

            $context .= 'Authorized tables list: '.($fallbackList !== '' ? $fallbackList : '(none)')."\n";
        }

        return [
            'context' => trim($context),
            'tables_included' => $tablesIncluded,
            'truncated' => $truncated,
            'skipped_tables' => $skippedTables,
        ];
    }

    /**
     * @param  array{name: string, schema: string|null}  $tableMeta
     */
    private function qualifiedTableName(array $tableMeta, string $dialect): string
    {
        $name = (string) $tableMeta['name'];
        $schema = is_string($tableMeta['schema']) && $tableMeta['schema'] !== ''
            ? $tableMeta['schema']
            : null;

        if ($dialect !== SqlDialectStrategy::DIALECT_POSTGRES || $schema === null) {
            return $name;
        }

        return sprintf('%s.%s', $schema, $name);
    }
}
