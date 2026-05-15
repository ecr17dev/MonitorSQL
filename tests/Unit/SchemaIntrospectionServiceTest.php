<?php

namespace Tests\Unit;

use App\Models\DatabaseConnection;
use App\Services\ConnectionService;
use App\Services\SchemaIntrospectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SchemaIntrospectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createConnection(string $driver = 'pgsql'): DatabaseConnection
    {
        return new DatabaseConnection([
            'name' => 'test-conn',
            'driver' => $driver,
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'test_db',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }

    public function test_describe_foreign_keys_returns_empty_for_table_without_fks(): void
    {
        $connection = $this->createConnection('pgsql');

        $connectionService = Mockery::mock(ConnectionService::class);
        $connectionService->shouldReceive('registerRuntimeConnection')
            ->once()
            ->andReturn('_runtime_test_conn');

        DB::shouldReceive('connection')
            ->with('_runtime_test_conn')
            ->andReturn($query = Mockery::mock());

        $query->shouldReceive('table')
            ->with('information_schema.table_constraints as tc')
            ->andReturnSelf();
        $query->shouldReceive('selectRaw')
            ->andReturnSelf();
        $query->shouldReceive('join')
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->andReturnSelf();
        $query->shouldReceive('orderBy')
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->andReturn(collect([]));

        DB::shouldReceive('purge')
            ->once()
            ->with('_runtime_test_conn');

        $service = new SchemaIntrospectionService($connectionService);

        $result = $service->describeForeignKeys($connection, 'public.products');

        $this->assertSame([], $result);
    }

    public function test_describe_foreign_keys_returns_fks_for_table_with_relationships(): void
    {
        $connection = $this->createConnection('pgsql');

        $connectionService = Mockery::mock(ConnectionService::class);
        $connectionService->shouldReceive('registerRuntimeConnection')
            ->once()
            ->andReturn('_runtime_test_conn');

        $fakeRows = collect([
            (object) [
                'column_name' => 'user_id',
                'referenced_table_name' => 'users',
                'referenced_column_name' => 'id',
            ],
            (object) [
                'column_name' => 'product_id',
                'referenced_table_name' => 'products',
                'referenced_column_name' => 'id',
            ],
        ]);

        DB::shouldReceive('connection')
            ->with('_runtime_test_conn')
            ->andReturn($query = Mockery::mock());

        $query->shouldReceive('table')->andReturnSelf();
        $query->shouldReceive('selectRaw')->andReturnSelf();
        $query->shouldReceive('join')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderBy')->andReturnSelf();
        $query->shouldReceive('get')
            ->andReturn($fakeRows);

        DB::shouldReceive('purge')->once()->with('_runtime_test_conn');

        $service = new SchemaIntrospectionService($connectionService);

        $result = $service->describeForeignKeys($connection, 'public.orders');

        $this->assertCount(2, $result);
        $this->assertSame('user_id', $result[0]['column']);
        $this->assertSame('users', $result[0]['referenced_table']);
        $this->assertSame('id', $result[0]['referenced_column']);
        $this->assertSame('product_id', $result[1]['column']);
        $this->assertSame('products', $result[1]['referenced_table']);
        $this->assertSame('id', $result[1]['referenced_column']);
    }

    public function test_describe_foreign_keys_with_mysql_uses_database_query(): void
    {
        $connection = $this->createConnection('mysql');

        $connectionService = Mockery::mock(ConnectionService::class);
        $connectionService->shouldReceive('registerRuntimeConnection')
            ->once()
            ->andReturn('_runtime_test_conn');

        $fakeRows = collect([
            (object) [
                'column_name' => 'user_id',
                'referenced_table_name' => 'users',
                'referenced_column_name' => 'id',
            ],
        ]);

        DB::shouldReceive('connection')
            ->with('_runtime_test_conn')
            ->andReturn($query = Mockery::mock());

        $query->shouldReceive('table')
            ->with('information_schema.key_column_usage as kcu')
            ->andReturnSelf();
        $query->shouldReceive('selectRaw')->andReturnSelf();
        $query->shouldReceive('whereRaw')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereNotNull')->andReturnSelf();
        $query->shouldReceive('orderBy')->andReturnSelf();
        $query->shouldReceive('get')
            ->andReturn($fakeRows);

        DB::shouldReceive('purge')->once()->with('_runtime_test_conn');

        $service = new SchemaIntrospectionService($connectionService);

        $result = $service->describeForeignKeys($connection, 'orders');

        $this->assertCount(1, $result);
        $this->assertSame('user_id', $result[0]['column']);
        $this->assertSame('users', $result[0]['referenced_table']);
        $this->assertSame('id', $result[0]['referenced_column']);
    }
}
