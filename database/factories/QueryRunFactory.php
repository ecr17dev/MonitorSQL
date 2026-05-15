<?php

namespace Database\Factories;

use App\Models\QueryRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueryRun>
 */
class QueryRunFactory extends Factory
{
    protected $model = QueryRun::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'connection_id' => null,
            'sql' => fake()->sentence(),
            'normalized_sql' => null,
            'sql_hash' => null,
            'status' => 'success',
            'category' => null,
            'tags' => null,
            'note' => null,
            'is_favorite' => false,
            'duration_ms' => fake()->numberBetween(0, 5000),
            'rows_returned' => fake()->numberBetween(0, 100),
            'is_ai_generated' => false,
            'error_message' => null,
            'meta' => null,
        ];
    }
}
