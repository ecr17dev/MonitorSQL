<?php

namespace App\Services;

class SqlDialectStrategy
{
    public const DIALECT_MYSQL = 'mysql';

    public const DIALECT_MARIADB = 'mariadb';

    public const DIALECT_POSTGRES = 'pgsql';

    /**
     * @return self::DIALECT_MYSQL|self::DIALECT_MARIADB|self::DIALECT_POSTGRES
     */
    public function resolveForDriver(string $driver): string
    {
        $normalized = strtolower(trim($driver));

        return match ($normalized) {
            self::DIALECT_MYSQL, self::DIALECT_MARIADB, self::DIALECT_POSTGRES => $normalized,
            default => self::DIALECT_POSTGRES,
        };
    }

    public function promptRules(string $dialect): string
    {
        return match ($this->resolveForDriver($dialect)) {
            self::DIALECT_MYSQL, self::DIALECT_MARIADB => <<<'RULES'
Dialect: MySQL / MariaDB
- Use DATE_ADD(date_col, INTERVAL 7 DAY) and DATE_SUB(date_col, INTERVAL 1 MONTH) for date arithmetic.
- For case-insensitive filtering, prefer LOWER(column) LIKE LOWER('%term%') when collation is uncertain.
- Use JSON_EXTRACT(data, '$.key') or data->>'$.key' for JSON text extraction.
- Avoid PostgreSQL-only syntax like ILIKE, DATE_TRUNC, and ::type casts.
- Keep read-only analytical patterns from sql-queries: CTEs, window functions, deduplication, cohort and funnel analysis.
RULES,
            self::DIALECT_POSTGRES => <<<'RULES'
Dialect: PostgreSQL
- Use DATE_TRUNC('month', created_at), INTERVAL arithmetic, and EXTRACT for date/time logic.
- Use ILIKE for case-insensitive matching and ~ / REGEXP_REPLACE for regex.
- Use JSON operators (->, ->>, #>>) and ARRAY operations when needed.
- Avoid warehouse-specific syntax like DATEADD, DATEDIFF, QUALIFY, and LATERAL FLATTEN.
- Keep read-only analytical patterns from sql-queries: CTEs, window functions, deduplication, cohort and funnel analysis.
RULES,
        };
    }

    /**
     * @return array{is_valid: bool, errors: array<int, string>}
     */
    public function validateSqlCompatibility(string $sql, string $dialect): array
    {
        $errors = [];

        foreach ($this->dialectIncompatibleRules($dialect) as $rule) {
            if (preg_match($rule['pattern'], $sql) === 1) {
                $errors[] = $rule['message'];
            }
        }

        return [
            'is_valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    public function adaptationNote(string $dialect): string
    {
        return match ($this->resolveForDriver($dialect)) {
            self::DIALECT_MYSQL, self::DIALECT_MARIADB => 'La consulta se adaptó al dialecto MySQL/MariaDB.',
            self::DIALECT_POSTGRES => 'La consulta se adaptó al dialecto PostgreSQL.',
        };
    }

    /**
     * @return array<int, array{pattern: string, message: string}>
     */
    private function dialectIncompatibleRules(string $dialect): array
    {
        return match ($this->resolveForDriver($dialect)) {
            self::DIALECT_MYSQL, self::DIALECT_MARIADB => [
                [
                    'pattern' => '/\bilike\b/i',
                    'message' => 'Dialect mismatch: ILIKE is not valid for MySQL/MariaDB. Use LOWER(column) LIKE LOWER(...) instead.',
                ],
                [
                    'pattern' => '/\bdate_trunc\s*\(/i',
                    'message' => 'Dialect mismatch: DATE_TRUNC is not valid for MySQL/MariaDB. Use DATE_FORMAT or explicit date extraction.',
                ],
                [
                    'pattern' => '/::\s*[a-z_][a-z0-9_]*/i',
                    'message' => 'Dialect mismatch: PostgreSQL style casts (::type) are not valid for MySQL/MariaDB.',
                ],
                [
                    'pattern' => '/\bqualify\b/i',
                    'message' => 'Dialect mismatch: QUALIFY is not valid for MySQL/MariaDB.',
                ],
                [
                    'pattern' => '/\blateral\s+flatten\b/i',
                    'message' => 'Dialect mismatch: Snowflake FLATTEN syntax is not valid for MySQL/MariaDB.',
                ],
                [
                    'pattern' => '/\b(timestamp_trunc|format_timestamp|safe_divide|result_scan)\s*\(/i',
                    'message' => 'Dialect mismatch: warehouse-specific functions were detected and are not executable in MySQL/MariaDB.',
                ],
            ],
            self::DIALECT_POSTGRES => [
                [
                    'pattern' => '/\b(dateadd|datediff|timestamp_trunc|format_timestamp|safe_divide|result_scan)\s*\(/i',
                    'message' => 'Dialect mismatch: warehouse-specific date/time function detected for PostgreSQL.',
                ],
                [
                    'pattern' => '/\bqualify\b/i',
                    'message' => 'Dialect mismatch: QUALIFY is not valid for PostgreSQL.',
                ],
                [
                    'pattern' => '/\blateral\s+flatten\b/i',
                    'message' => 'Dialect mismatch: Snowflake FLATTEN syntax is not valid for PostgreSQL.',
                ],
                [
                    'pattern' => '/\bzorder\b/i',
                    'message' => 'Dialect mismatch: ZORDER is a Databricks optimization command, not PostgreSQL SQL.',
                ],
            ],
        };
    }
}
