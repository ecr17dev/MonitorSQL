<?php

namespace Database\Factories;

use App\Models\SavedQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedQuery>
 */
class SavedQueryFactory extends Factory
{
    protected $model = SavedQuery::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'connection_id' => null,
            'name' => fake()->words(3, true),
            'sql' => 'SELECT * FROM '.fake()->word(),
            'is_favorite' => false,
            'category' => null,
            'tags' => null,
            'note' => null,
        ];
    }
}
