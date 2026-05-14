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

        $tableList = $this->schemaIntrospectionService->listTables($connection);

        $tableByName = collect($tableList)
            ->filter(fn (array $table): bool => ! empty($table['name']))
            ->keyBy(fn (array $table): string => (string) $table['name']);

        $orderedCandidates = collect([...$selectedTables, ...$allowedTables])
            ->filter(fn (mixed $table): bool => is_string($table) && $table !== '')
            ->unique()
            ->values();

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
                continue;
            }

            $qualifiedName = $this->qualifiedTableName($tableMeta, $dialect);

            try {
                $columns = $this->schemaIntrospectionService->describeTable($connection, $qualifiedName);
            } catch (\Throwable) {
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

            $section = sprintf("Table: %s\n%s\n\n", $qualifiedName, $columnLines);

            if (strlen($context.$section) > $maxChars) {
                $truncated = true;
                break;
            }

            $context .= $section;
            $tablesIncluded[] = (string) $tableName;
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
