<?php

namespace Tests\Feature;

use App\Ai\Agents\SqlQueryAssistant;
use App\Jobs\ProcessDataExportJob;
use App\Models\ConnectionPermission;
use App\Models\DatabaseConnection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ReadOnlyQueryExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class MonitorSqlQueryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_validation_blocks_write_statements()
    {
        $user = $this->createUserWithPermission('queries.execute');
        $connection = $this->createConnection();
        $this->grantTableAccess($user, $connection, 'customers');

        $response = $this->actingAs($user)->postJson('/queries/validate', [
            'connection_id' => $connection->id,
            'sql' => 'DELETE FROM customers',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('is_valid', false);
    }

    public function test_query_validation_blocks_multi_statement_bypass()
    {
        $user = $this->createUserWithPermission('queries.execute');
        $connection = $this->createConnection();
        $this->grantTableAccess($user, $connection, 'customers');

        $response = $this->actingAs($user)->postJson('/queries/validate', [
            'connection_id' => $connection->id,
            'sql' => 'SELECT * FROM customers; DROP TABLE users;',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('is_valid', false);
    }

    public function test_query_execute_returns_structured_contract()
    {
        $user = $this->createUserWithPermission('queries.execute');
        $connection = $this->createConnection();
        $this->grantTableAccess($user, $connection, 'customers');

        $this->mock(ReadOnlyQueryExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn([
                    'columns' => [
                        ['name' => 'id', 'type' => 'int'],
                        ['name' => 'name', 'type' => 'string'],
                    ],
                    'rows' => [
                        ['id' => 1, 'name' => 'Jane'],
                    ],
                    'meta' => [
                        'duration_ms' => 42,
                        'row_count' => 1,
                        'limited' => true,
                    ],
                ]);
        });

        $response = $this->actingAs($user)->postJson('/queries/execute', [
            'connection_id' => $connection->id,
            'sql' => 'SELECT * FROM customers',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'columns',
            'rows',
            'meta' => ['duration_ms', 'row_count', 'limited'],
        ]);
    }

    public function test_query_execute_fails_for_disallowed_tables()
    {
        $user = $this->createUserWithPermission('queries.execute');
        $connection = $this->createConnection();
        $this->grantTableAccess($user, $connection, 'customers');

        $response = $this->actingAs($user)->postJson('/queries/execute', [
            'connection_id' => $connection->id,
            'sql' => 'SELECT * FROM orders',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'SQL validation failed.');
    }

    public function test_ai_generate_returns_structured_payload_with_confirmation()
    {
        SqlQueryAssistant::fake([
            [
                'sql' => 'SELECT * FROM customers LIMIT 10',
                'explanation' => 'Fetches customer rows.',
                'tables_used' => ['customers'],
                'confidence' => 'high',
                'suggested_visualization' => [
                    'type' => 'table',
                    'x_axis' => null,
                    'y_axis' => null,
                    'reason' => 'Rows are tabular.',
                ],
            ],
        ]);

        $user = $this->createUserWithPermission('queries.ai_generate');
        $connection = $this->createConnection();
        $this->grantTableAccess($user, $connection, 'customers');

        $response = $this->actingAs($user)->postJson('/queries/ai-generate', [
            'connection_id' => $connection->id,
            'question' => 'Count customers',
            'selected_tables' => ['customers'],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'sql',
            'explanation',
            'tables_used',
            'confidence',
            'requires_confirmation',
            'suggested_visualization' => ['type', 'x_axis', 'y_axis', 'reason'],
        ]);
        $response->assertJsonPath('requires_confirmation', true);
    }

    public function test_exports_can_be_queued_in_xlsx()
    {
        Queue::fake();

        $user = $this->createUserWithPermission('queries.export');
        $connection = $this->createConnection();
        $this->grantTableAccess($user, $connection, 'customers');

        $response = $this->actingAs($user)->postJson('/exports', [
            'connection_id' => $connection->id,
            'sql' => 'SELECT * FROM customers',
            'format' => 'xlsx',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('export.status', 'pending');

        Queue::assertPushed(ProcessDataExportJob::class);
    }

    public function test_audit_endpoint_supports_filters()
    {
        $user = $this->createUserWithPermission('audit.view');

        $response = $this->actingAs($user)->getJson('/audit?action=query.executed&status=success');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_connection_table_preview_forbidden_without_scope()
    {
        $user = $this->createUserWithPermission('tables.view');
        $connection = $this->createConnection();

        $response = $this->actingAs($user)->getJson("/connections/{$connection->id}/tables/customers/preview");

        $response->assertStatus(403);
    }

    public function test_query_execute_returns_sanitized_message_on_engine_error()
    {
        $user = $this->createUserWithPermission('queries.execute');
        $connection = $this->createConnection();
        $this->grantTableAccess($user, $connection, 'customers');

        $this->mock(ReadOnlyQueryExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('driver error'));
        });

        $response = $this->actingAs($user)->postJson('/queries/execute', [
            'connection_id' => $connection->id,
            'sql' => 'SELECT * FROM customers',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'The SQL engine returned a sanitized error response.');
    }

    private function createUserWithPermission(string $permissionKey): User
    {
        $permission = Permission::query()->firstOrCreate([
            'name' => $permissionKey,
            'key' => $permissionKey,
        ]);

        $role = Role::query()->create([
            'name' => 'Test Role',
            'key' => 'test-role-'.str_replace('.', '-', $permissionKey).'-'.fake()->randomNumber(3),
        ]);

        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createConnection(): DatabaseConnection
    {
        return DatabaseConnection::query()->create([
            'name' => 'Local PG',
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'demo',
            'username' => 'readonly',
            'password' => 'secret',
            'ssl_enabled' => false,
            'is_active' => true,
            'max_rows' => 100,
            'query_timeout_seconds' => 30,
        ]);
    }

    private function grantTableAccess(User $user, DatabaseConnection $connection, string $table): void
    {
        ConnectionPermission::query()->create([
            'connection_id' => $connection->id,
            'user_id' => $user->id,
            'table_name' => $table,
            'can_view' => true,
        ]);
    }
}
