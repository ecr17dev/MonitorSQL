<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConnectionService
{
    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(DatabaseConnection $connection): array
    {
        $connectionName = $this->registerRuntimeConnection($connection);

        try {
            DB::connection($connectionName)->getPdo();

            return [
                'success' => true,
                'message' => 'Connection successful.',
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Connection failed. Verify host, credentials, and SSL options.',
            ];
        } finally {
            DB::purge($connectionName);
        }
    }

    public function registerRuntimeConnection(DatabaseConnection $connection): string
    {
        $connectionName = sprintf('monitor_external_%d', $connection->id);

        Config::set(sprintf('database.connections.%s', $connectionName), $this->buildConfig($connection));

        DB::purge($connectionName);

        return $connectionName;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConfig(DatabaseConnection $connection): array
    {
        $driver = $connection->driver;

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return [
                'driver' => 'mysql',
                'host' => $connection->host,
                'port' => $connection->port,
                'database' => $connection->database,
                'username' => $connection->username,
                'password' => $connection->password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => [],
            ];
        }

        return [
            'driver' => 'pgsql',
            'host' => $connection->host,
            'port' => $connection->port,
            'database' => $connection->database,
            'username' => $connection->username,
            'password' => $connection->password,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => $connection->ssl_enabled ? 'require' : 'disable',
        ];
    }
}
