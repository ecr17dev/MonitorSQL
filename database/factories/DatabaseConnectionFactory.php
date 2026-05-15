<?php

namespace Database\Factories;

use App\Models\DatabaseConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatabaseConnection>
 */
class DatabaseConnectionFactory extends Factory
{
    protected $model = DatabaseConnection::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => fake()->word(),
            'username' => 'postgres',
            'password' => 'secret',
            'ssl_enabled' => false,
            'is_active' => true,
            'max_rows' => 1000,
            'query_timeout_seconds' => 30,
            'last_tested_at' => null,
            'created_by' => null,
        ];
    }
}
