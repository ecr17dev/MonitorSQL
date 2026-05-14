<?php

namespace Tests\Feature;

use App\Ai\Agents\SqlQueryAssistant;
use App\Jobs\ProcessDataExportJob;
use App\Models\AiMemoryProfile;
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

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.conversations.generate_title', false);
    }

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
            'conversation_id',
            'dialect',
            'memory_applied' => ['short_term', 'long_term'],
            'requires_confirmation',
            'suggested_visualization' => ['type', 'x_axis', 'y_axis', 'reason'],
        ]);
        $response->assertJsonPath('requires_confirmation', true);
        $response->assertJsonPath('dialect', 'pgsql');
        $response->assertJsonPath('memory_applied.short_term', true);
        $response->assertJsonPath('conversation_id', fn (mixed $value): bool => is_string($value) && $value !== '');

        $profile = AiMemoryProfile::query()
            ->where('user_id', $user->id)
            ->where('connection_id', $connection->id)
            ->first();

        $this->assertNotNull($profile);
        $this->assertIsArray($profile?->preferred_tables);
    }

    public function test_ai_generate_continues_existing_conversation_for_same_user()
    {
        SqlQueryAssistant::fake([
            [
                'sql' => 'SELECT * FROM customers LIMIT 10',
                'explanation' => 'First response.',
                'tables_used' => ['customers'],
                'confidence' => 'high',
                'suggested_visualization' => [
                    'type' => 'table',
                    'x_axis' => null,
                    'y_axis' => null,
                    'reason' => 'Rows are tabular.',
                ],
            ],
            [
                'sql' => 'SELECT COUNT(*) AS total FROM customers',
                'explanation' => 'Second response.',
                'tables_used' => ['customers'],
                'confidence' => 'high',
                'suggested_visualization' => [
                    'type' => 'kpi',
                    'x_axis' => null,
                    'y_axis' => 'total',
                    'reason' => 'Single metric.',
                ],
            ],
        ]);

        $user = $this->createUserWithPermission('queries.ai_generate');
        $connection = $this->createConnection();
        $this->grantTableAccess($user, $connection, 'customers');

        $first = $this->actingAs($user)->postJson('/queries/ai-generate', [
            'connection_id' => $connection->id,
            'question' => 'List customers',
            'selected_tables' => ['customers'],
        ]);

        $first->assertOk();
        $conversationId = $first->json('conversation_id');
        $this->assertIsString($conversationId);

        $second = $this->actingAs($user)->postJson('/queries/ai-generate', [
            'connection_id' => $connection->id,
            'question' => 'Now count them',
            'conversation_id' => $conversationId,
            'selected_tables' => ['customers'],
        ]);

        $second->assertOk();
        $second->assertJsonPath('conversation_id', $conversationId);
    }

    public function test_ai_generate_rejects_conversation_id_from_another_user()
    {
        SqlQueryAssistant::fake([
            [
                'sql' => 'SELECT * FROM customers LIMIT 10',
                'explanation' => 'First response.',
                'tables_used' => ['customers'],
                'confidence' => 'high',
                'suggested_visualization' => [
                    'type' => 'table',
                    'x_axis' => null,
                    'y_axis' => null,
                    'reason' => 'Rows are tabular.',
                ],
            ],
            [
                'sql' => 'SELECT * FROM customers LIMIT 5',
                'explanation' => 'Other user response.',
                'tables_used' => ['customers'],
                'confidence' => 'medium',
                'suggested_visualization' => [
                    'type' => 'table',
                    'x_axis' => null,
                    'y_axis' => null,
                    'reason' => 'Rows are tabular.',
                ],
            ],
        ]);

        $owner = $this->createUserWithPermission('queries.ai_generate');
        $intruder = $this->createUserWithPermission('queries.ai_generate');
        $connection = $this->createConnection();
        $this->grantTableAccess($owner, $connection, 'customers');
        $this->grantTableAccess($intruder, $connection, 'customers');

        $first = $this->actingAs($owner)->postJson('/queries/ai-generate', [
            'connection_id' => $connection->id,
            'question' => 'List customers',
            'selected_tables' => ['customers'],
        ]);

        $first->assertOk();
        $conversationId = $first->json('conversation_id');
        $this->assertIsString($conversationId);

        $rejected = $this->actingAs($intruder)->postJson('/queries/ai-generate', [
            'connection_id' => $connection->id,
            'question' => 'Reuse that conversation',
            'conversation_id' => $conversationId,
            'selected_tables' => ['customers'],
        ]);

        $rejected->assertStatus(422);
        $rejected->assertJsonPath('message', 'The provided conversation_id is invalid for this user.');
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
        $response->assertJsonPath('message', 'Error de base de datos: driver error');
        $response->assertJsonPath('sql', 'SELECT * FROM customers LIMIT 100');
    }

    public function test_query_execute_blocks_dialect_mismatch_for_mysql_connection()
    {
        $user = $this->createUserWithPermission('queries.execute');
        $connection = $this->createConnection(driver: 'mysql');
        $this->grantTableAccess($user, $connection, 'customers');

        $response = $this->actingAs($user)->postJson('/queries/execute', [
            'connection_id' => $connection->id,
            'sql' => "SELECT * FROM customers WHERE name ILIKE '%john%'",
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'SQL validation failed.');
        $this->assertTrue(
            collect($response->json('errors', []))
                ->contains(fn (mixed $error): bool => is_string($error) && str_contains($error, 'Dialect mismatch'))
        );
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

    private function createConnection(string $driver = 'pgsql'): DatabaseConnection
    {
        $defaultPort = $driver === 'pgsql' ? 5432 : 3306;

        return DatabaseConnection::query()->create([
            'name' => 'Local PG',
            'driver' => $driver,
            'host' => '127.0.0.1',
            'port' => $defaultPort,
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
