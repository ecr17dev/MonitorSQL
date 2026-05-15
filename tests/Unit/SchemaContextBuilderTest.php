<?php

namespace Tests\Unit;

use App\Models\DatabaseConnection;
use App\Services\SchemaContextBuilder;
use App\Services\SchemaIntrospectionService;
use App\Services\SqlDialectStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SchemaContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function createConnection(string $driver = 'pgsql'): DatabaseConnection
    {
        return new DatabaseConnection([
            'name' => 'test',
            'driver' => $driver,
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'test_db',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }

    public function test_build_includes_foreign_keys_for_allowed_referenced_tables(): void
    {
        $connection = $this->createConnection('pgsql');

        $schemaIntrospection = Mockery::mock(SchemaIntrospectionService::class);
        $dialectStrategy = new SqlDialectStrategy;

        $tables = [
            ['name' => 'leads', 'schema' => null],
            ['name' => 'quotes', 'schema' => null],
            ['name' => 'users', 'schema' => null],
        ];

        $leadsColumns = [
            ['name' => 'id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'name', 'type' => 'varchar', 'nullable' => false],
            ['name' => 'user_id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'created_at', 'type' => 'timestamp', 'nullable' => true],
        ];

        $quotesColumns = [
            ['name' => 'id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'lead_id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'amount', 'type' => 'numeric', 'nullable' => true],
            ['name' => 'created_at', 'type' => 'timestamp', 'nullable' => true],
        ];

        $usersColumns = [
            ['name' => 'id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'email', 'type' => 'varchar', 'nullable' => false],
        ];

        $leadsFKs = [
            ['column' => 'user_id', 'referenced_table' => 'users', 'referenced_column' => 'id'],
        ];

        $quotesFKs = [
            ['column' => 'lead_id', 'referenced_table' => 'leads', 'referenced_column' => 'id'],
        ];

        $schemaIntrospection->shouldReceive('listTables')
            ->once()
            ->with($connection)
            ->andReturn($tables);

        $schemaIntrospection->shouldReceive('describeTable')
            ->with($connection, 'leads')
            ->andReturn($leadsColumns);
        $schemaIntrospection->shouldReceive('describeForeignKeys')
            ->with($connection, 'leads')
            ->andReturn($leadsFKs);

        $schemaIntrospection->shouldReceive('describeTable')
            ->with($connection, 'quotes')
            ->andReturn($quotesColumns);
        $schemaIntrospection->shouldReceive('describeForeignKeys')
            ->with($connection, 'quotes')
            ->andReturn($quotesFKs);

        $schemaIntrospection->shouldReceive('describeTable')
            ->with($connection, 'users')
            ->andReturn($usersColumns);
        $schemaIntrospection->shouldReceive('describeForeignKeys')
            ->with($connection, 'users')
            ->andReturn([]);

        $builder = new SchemaContextBuilder($schemaIntrospection, $dialectStrategy);

        $allowedTables = ['leads', 'quotes', 'users'];

        $result = $builder->build($connection, $allowedTables);

        $this->assertArrayHasKey('context', $result);
        $this->assertArrayHasKey('tables_included', $result);
        $this->assertArrayHasKey('truncated', $result);

        $context = $result['context'];

        $this->assertStringContainsString('Active SQL dialect: pgsql', $context);
        $this->assertStringContainsString('Table: leads', $context);
        $this->assertStringContainsString('Foreign keys (to allowed tables)', $context);
        $this->assertStringContainsString('- user_id -> users(id)', $context);
        $this->assertStringContainsString('- lead_id -> leads(id)', $context);
        $this->assertStringContainsString('Table: quotes', $context);
        $this->assertStringContainsString('Table: users', $context);
        $this->assertSame(['leads', 'quotes', 'users'], $result['tables_included']);
    }

    public function test_build_shows_restricted_foreign_keys_with_warning(): void
    {
        $connection = $this->createConnection('pgsql');

        $schemaIntrospection = Mockery::mock(SchemaIntrospectionService::class);
        $dialectStrategy = new SqlDialectStrategy;

        $tables = [
            ['name' => 'orders', 'schema' => null],
        ];

        $ordersColumns = [
            ['name' => 'id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'user_id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'secret_table_id', 'type' => 'integer', 'nullable' => true],
        ];

        $ordersFKs = [
            ['column' => 'user_id', 'referenced_table' => 'users', 'referenced_column' => 'id'],
            ['column' => 'secret_table_id', 'referenced_table' => 'secret_table', 'referenced_column' => 'id'],
        ];

        $schemaIntrospection->shouldReceive('listTables')
            ->once()
            ->with($connection)
            ->andReturn($tables);

        $schemaIntrospection->shouldReceive('describeTable')
            ->with($connection, 'orders')
            ->andReturn($ordersColumns);
        $schemaIntrospection->shouldReceive('describeForeignKeys')
            ->with($connection, 'orders')
            ->andReturn($ordersFKs);

        $builder = new SchemaContextBuilder($schemaIntrospection, $dialectStrategy);

        $allowedTables = ['orders', 'users'];

        $result = $builder->build($connection, $allowedTables);

        $context = $result['context'];

        $this->assertStringContainsString('- user_id -> users(id)', $context);
        $this->assertStringContainsString('Foreign keys (to RESTRICTED tables', $context);
        $this->assertStringContainsString('[WARNING: referenced table "secret_table" is NOT available for JOIN', $context);
    }

    public function test_build_with_selected_tables_prioritizes_them(): void
    {
        $connection = $this->createConnection('pgsql');

        $schemaIntrospection = Mockery::mock(SchemaIntrospectionService::class);
        $dialectStrategy = new SqlDialectStrategy;

        $tables = [
            ['name' => 'leads', 'schema' => null],
            ['name' => 'quotes', 'schema' => null],
        ];

        $leadsColumns = [['name' => 'id', 'type' => 'integer', 'nullable' => false]];
        $quotesColumns = [['name' => 'id', 'type' => 'integer', 'nullable' => false]];

        $quotesFKs = [
            ['column' => 'lead_id', 'referenced_table' => 'leads', 'referenced_column' => 'id'],
        ];

        $schemaIntrospection->shouldReceive('listTables')
            ->once()
            ->with($connection)
            ->andReturn($tables);

        $schemaIntrospection->shouldReceive('describeTable')
            ->with($connection, 'quotes')
            ->andReturn($quotesColumns);
        $schemaIntrospection->shouldReceive('describeForeignKeys')
            ->with($connection, 'quotes')
            ->andReturn($quotesFKs);

        $schemaIntrospection->shouldReceive('describeTable')
            ->with($connection, 'leads')
            ->andReturn($leadsColumns);
        $schemaIntrospection->shouldReceive('describeForeignKeys')
            ->with($connection, 'leads')
            ->andReturn([]);

        $builder = new SchemaContextBuilder($schemaIntrospection, $dialectStrategy);

        $allowedTables = ['leads', 'quotes'];
        $selectedTables = ['quotes'];

        $result = $builder->build($connection, $allowedTables, $selectedTables);

        $context = $result['context'];

        $this->assertStringContainsString('Table: quotes', $context);
        $this->assertStringContainsString('- lead_id -> leads(id)', $context);
    }
}
