<?php

namespace App\Services;

use App\Models\AiMemoryProfile;
use App\Models\User;
use Illuminate\Support\Str;

class AiMemoryProfileService
{
    /**
     * @return array{context: string, applied: bool}
     */
    public function promptContext(User $user, int $connectionId): array
    {
        $profile = AiMemoryProfile::query()
            ->where('user_id', $user->id)
            ->where('connection_id', $connectionId)
            ->first();

        if (! $profile) {
            return [
                'context' => '',
                'applied' => false,
            ];
        }

        $preferredTables = $this->topCounts($profile->preferred_tables ?? []);
        $patterns = $this->topCounts($profile->successful_query_patterns ?? []);
        $aliases = collect($profile->term_aliases ?? [])->take(8);

        $lines = ['Long-term memory signals (user + connection):'];

        if ($preferredTables !== []) {
            $lines[] = 'Preferred tables: '.collect($preferredTables)->keys()->implode(', ');
        }

        if ($aliases->isNotEmpty()) {
            $aliasText = $aliases
                ->map(fn (mixed $table, string $term): string => sprintf('%s=>%s', $term, (string) $table))
                ->implode(', ');
            $lines[] = 'Business term aliases: '.$aliasText;
        }

        if ($patterns !== []) {
            $lines[] = 'Successful analytical patterns: '.collect($patterns)->keys()->implode(', ');
        }

        return [
            'context' => implode("\n", $lines),
            'applied' => count($lines) > 1,
        ];
    }

    /**
     * @param  array<int, string>  $tablesUsed
     */
    public function recordGeneratedSuggestion(User $user, int $connectionId, string $question, string $sql, array $tablesUsed): void
    {
        $profile = $this->findOrCreate($user, $connectionId);

        $profile->preferred_tables = $this->incrementCounts(
            $profile->preferred_tables ?? [],
            $tablesUsed,
        );

        $profile->term_aliases = $this->mergeTermAliases(
            $profile->term_aliases ?? [],
            $this->extractTerms($question),
            $tablesUsed[0] ?? null,
        );

        $profile->successful_query_patterns = $this->incrementCounts(
            $profile->successful_query_patterns ?? [],
            [$this->detectPattern($sql)],
        );

        $profile->last_used_at = now();
        $profile->save();
    }

    /**
     * @param  array<int, string>  $tablesUsed
     */
    public function recordSuccessfulExecution(User $user, int $connectionId, string $sql, array $tablesUsed): void
    {
        $profile = $this->findOrCreate($user, $connectionId);

        $profile->preferred_tables = $this->incrementCounts(
            $profile->preferred_tables ?? [],
            $tablesUsed,
        );

        $profile->successful_query_patterns = $this->incrementCounts(
            $profile->successful_query_patterns ?? [],
            [$this->detectPattern($sql)],
        );

        $profile->last_used_at = now();
        $profile->save();
    }

    private function findOrCreate(User $user, int $connectionId): AiMemoryProfile
    {
        return AiMemoryProfile::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'connection_id' => $connectionId,
            ],
            [
                'preferred_tables' => [],
                'term_aliases' => [],
                'successful_query_patterns' => [],
                'last_used_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $keys
     * @return array<string, int>
     */
    private function incrementCounts(array $counts, array $keys): array
    {
        $normalized = collect($counts)
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => (int) $value])
            ->all();

        foreach ($keys as $key) {
            $clean = trim((string) $key);

            if ($clean === '') {
                continue;
            }

            $normalized[$clean] = ($normalized[$clean] ?? 0) + 1;
        }

        arsort($normalized);

        return array_slice($normalized, 0, 25, true);
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @param  array<int, string>  $terms
     * @return array<string, string>
     */
    private function mergeTermAliases(array $aliases, array $terms, ?string $table): array
    {
        if (! is_string($table) || $table === '') {
            return $aliases;
        }

        $result = collect($aliases)
            ->mapWithKeys(fn (mixed $value, string $term): array => [$term => (string) $value])
            ->all();

        foreach ($terms as $term) {
            $result[$term] = $table;
        }

        return array_slice($result, 0, 50, true);
    }

    /**
     * @return array<int, string>
     */
    private function extractTerms(string $question): array
    {
        $stopwords = [
            'que', 'qué', 'como', 'cómo', 'para', 'con', 'sin', 'dame', 'mostrar', 'muestrame',
            'quiero', 'need', 'show', 'list', 'from', 'where', 'this', 'that', 'the', 'los', 'las',
            'por', 'del', 'una', 'uno', 'unos', 'unas', 'and', 'pero', 'porque', 'sobre', 'datos',
        ];

        return collect(preg_split('/[^\p{L}\p{N}_]+/u', Str::lower($question)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 4)
            ->reject(fn (string $term): bool => in_array($term, $stopwords, true))
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function topCounts(array $counts): array
    {
        $normalized = collect($counts)
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => (int) $value])
            ->sortDesc()
            ->take(6)
            ->all();

        return is_array($normalized) ? $normalized : [];
    }

    private function detectPattern(string $sql): string
    {
        $normalizedSql = Str::lower($sql);

        return match (true) {
            str_contains($normalizedSql, 'row_number() over') => 'deduplication_window',
            str_contains($normalizedSql, 'lag(') || str_contains($normalizedSql, 'lead(') => 'comparison_window',
            str_contains($normalizedSql, 'over (') => 'window_function',
            str_starts_with(trim($normalizedSql), 'with ') => 'cte_query',
            str_contains($normalizedSql, 'group by') => 'aggregation',
            str_contains($normalizedSql, 'count(') => 'counting',
            default => 'basic_select',
        };
    }
}
