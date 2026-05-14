<?php

namespace App\Services;

use Illuminate\Support\Str;

class QueryValidationService
{
    public function __construct(private readonly SqlDialectStrategy $sqlDialectStrategy) {}

    /**
     * @return array{
     *   is_valid: bool,
     *   normalized_sql: string,
     *   sql_with_limit: string,
     *   sql_hash: string,
     *   tables: array<int, string>,
     *   errors: array<int, string>,
     *   limited: bool
     * }
     */
    public function validate(string $sql, int $maxRows, array $allowedTables = [], ?string $dialect = null): array
    {
        $errors = [];
        $normalizedSql = $this->normalize($sql);
        $maxRows = max(1, $maxRows);

        if ($normalizedSql === '') {
            $errors[] = 'SQL query is required.';
        }

        if ($this->hasMultipleStatements($normalizedSql)) {
            $errors[] = 'Multiple SQL statements are not allowed.';
        }

        if (! $this->startsAsReadOnly($normalizedSql)) {
            $errors[] = 'Only SELECT queries are allowed.';
        }

        if ($this->hasBlockedTokens($normalizedSql)) {
            $errors[] = 'The SQL query includes non-read-only operations.';
        }

        if ($this->hasBlockedClauses($normalizedSql)) {
            $errors[] = 'The SQL query includes disallowed clauses.';
        }

        $tables = $this->extractTables($normalizedSql);

        if ($allowedTables !== [] && $tables !== []) {
            $blockedTable = collect($tables)
                ->reject(fn (string $table): bool => in_array($table, $allowedTables, true))
                ->first();

            if (is_string($blockedTable)) {
                $errors[] = sprintf('Table "%s" is not allowed for this user.', $blockedTable);
            }
        }

        if (is_string($dialect) && $dialect !== '') {
            $compatibility = $this->sqlDialectStrategy->validateSqlCompatibility($normalizedSql, $dialect);

            if (! $compatibility['is_valid']) {
                $errors = [...$errors, ...$compatibility['errors']];
            }
        }

        $sqlWithLimit = $this->appendLimit($normalizedSql, $maxRows);

        return [
            'is_valid' => $errors === [],
            'normalized_sql' => $normalizedSql,
            'sql_with_limit' => $sqlWithLimit,
            'sql_hash' => hash('sha256', $sqlWithLimit),
            'tables' => $tables,
            'errors' => $errors,
            'limited' => $this->isLimitInjected($normalizedSql),
        ];
    }

    public function isReadOnly(string $sql): bool
    {
        return $this->validate($sql, 1)['is_valid'];
    }

    private function normalize(string $sql): string
    {
        $stripped = $this->stripComments($sql);

        return trim((string) preg_replace('/\s+/', ' ', $stripped));
    }

    private function stripComments(string $sql): string
    {
        $output = '';
        $length = strlen($sql);
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if (! $inSingleQuote && ! $inDoubleQuote && ! $inBacktick && $char === '-' && $next === '-') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }

                $output .= ' ';

                continue;
            }

            if (! $inSingleQuote && ! $inDoubleQuote && ! $inBacktick && $char === '#') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }

                $output .= ' ';

                continue;
            }

            if (! $inSingleQuote && ! $inDoubleQuote && ! $inBacktick && $char === '/' && $next === '*') {
                $index += 2;

                while ($index < $length - 1 && ! ($sql[$index] === '*' && $sql[$index + 1] === '/')) {
                    $index++;
                }

                $index++;
                $output .= ' ';

                continue;
            }

            if ($char === "'" && ! $inDoubleQuote && ! $inBacktick) {
                $escaped = $index > 0 && $sql[$index - 1] === '\\';

                if (! $escaped) {
                    $inSingleQuote = ! $inSingleQuote;
                }
            }

            if ($char === '"' && ! $inSingleQuote && ! $inBacktick) {
                $escaped = $index > 0 && $sql[$index - 1] === '\\';

                if (! $escaped) {
                    $inDoubleQuote = ! $inDoubleQuote;
                }
            }

            if ($char === '`' && ! $inSingleQuote && ! $inDoubleQuote) {
                $inBacktick = ! $inBacktick;
            }

            $output .= $char;
        }

        return $output;
    }

    private function startsAsReadOnly(string $sql): bool
    {
        return preg_match('/^(select|with)\b/i', $sql) === 1;
    }

    private function hasMultipleStatements(string $sql): bool
    {
        $withoutStrings = $this->stripQuotedStrings($sql);
        $trimmed = rtrim($withoutStrings);

        while (Str::endsWith($trimmed, ';')) {
            $trimmed = rtrim(substr($trimmed, 0, -1));
        }

        return str_contains($trimmed, ';');
    }

    private function stripQuotedStrings(string $sql): string
    {
        $output = '';
        $length = strlen($sql);
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];

            if ($char === "'" && ! $inDoubleQuote && ! $inBacktick) {
                $escaped = $index > 0 && $sql[$index - 1] === '\\';

                if (! $escaped) {
                    $inSingleQuote = ! $inSingleQuote;
                }

                $output .= ' ';

                continue;
            }

            if ($char === '"' && ! $inSingleQuote && ! $inBacktick) {
                $escaped = $index > 0 && $sql[$index - 1] === '\\';

                if (! $escaped) {
                    $inDoubleQuote = ! $inDoubleQuote;
                }

                $output .= ' ';

                continue;
            }

            if ($char === '`' && ! $inSingleQuote && ! $inDoubleQuote) {
                $inBacktick = ! $inBacktick;
                $output .= ' ';

                continue;
            }

            if ($inSingleQuote || $inDoubleQuote || $inBacktick) {
                $output .= ' ';

                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function hasBlockedTokens(string $sql): bool
    {
        $withoutStrings = $this->stripQuotedStrings(Str::lower($sql));

        return (bool) preg_match(
            '/\b(insert|update|delete|drop|alter|truncate|create|replace|grant|revoke|exec|execute|call|merge|upsert|declare|do)\b/i',
            $withoutStrings,
        );
    }

    private function hasBlockedClauses(string $sql): bool
    {
        $withoutStrings = $this->stripQuotedStrings(Str::lower($sql));

        return (bool) preg_match(
            '/\b(into\s+outfile|into\s+dumpfile|load\s+data|copy\s+[^\s]+\s+from|copy\s*\(.+\)\s+to)\b/i',
            $withoutStrings,
        );
    }

    /**
     * @return array<int, string>
     */
    private function extractTables(string $sql): array
    {
        preg_match_all('/\b(?:from|join)\s+([a-zA-Z0-9_`".]+)/i', $sql, $matches);

        $tables = collect($matches[1] ?? [])
            ->map(function (string $table): string {
                $cleaned = Str::of($table)
                    ->replace('"', '')
                    ->replace('`', '')
                    ->replace("'", '')
                    ->trim()
                    ->toString();

                return Str::of($cleaned)->afterLast('.')->toString();
            })
            ->filter(fn (string $table): bool => preg_match('/^[a-zA-Z0-9_]+$/', $table) === 1)
            ->unique()
            ->values()
            ->all();

        return is_array($tables) ? $tables : [];
    }

    private function appendLimit(string $sql, int $maxRows): string
    {
        if ($this->hasLimit($sql)) {
            return $sql;
        }

        return sprintf('%s LIMIT %d', $sql, $maxRows);
    }

    private function hasLimit(string $sql): bool
    {
        $normalized = Str::lower($this->stripQuotedStrings($sql));

        return preg_match('/\blimit\s+\d+\b/', $normalized) === 1
            || preg_match('/\bfetch\s+first\s+\d+\s+rows\s+only\b/', $normalized) === 1;
    }

    private function isLimitInjected(string $sql): bool
    {
        return ! $this->hasLimit($sql);
    }
}
