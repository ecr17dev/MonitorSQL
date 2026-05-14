<?php

namespace Tests\Feature;

use App\Models\DatabaseConnection;
use App\Models\DataExport;
use App\Models\User;
use App\Services\ExportService;
use App\Services\ReadOnlyQueryExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class MonitorSqlExportProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_export_is_generated_with_native_xlsx_format()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $connection = DatabaseConnection::query()->create([
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

        $export = DataExport::query()->create([
            'user_id' => $user->id,
            'connection_id' => $connection->id,
            'format' => 'xlsx',
            'status' => 'pending',
            'sql' => 'SELECT * FROM customers',
        ]);

        $this->mock(ReadOnlyQueryExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andReturn([
                'columns' => [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'name', 'type' => 'string'],
                ],
                'rows' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'meta' => [
                    'duration_ms' => 10,
                    'row_count' => 2,
                    'limited' => true,
                ],
            ]);
        });

        /** @var ExportService $service */
        $service = $this->app->make(ExportService::class);
        $processed = $service->process($export);

        $this->assertSame('completed', $processed->status);
        $this->assertNotNull($processed->file_path);

        Storage::disk('local')->assertExists($processed->file_path);

        $contents = Storage::disk('local')->get($processed->file_path);
        $this->assertStringStartsWith('PK', $contents);
    }
}
