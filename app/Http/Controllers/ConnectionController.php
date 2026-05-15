<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDatabaseConnectionRequest;
use App\Http\Requests\UpdateDatabaseConnectionRequest;
use App\Models\DatabaseConnection;
use App\Services\ConnectionService;
use App\Services\SchemaIntrospectionService;
use App\Services\SchemaPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ConnectionController extends Controller
{
    public function __construct(
        private readonly ConnectionService $connectionService,
        private readonly SchemaIntrospectionService $schemaIntrospectionService,
        private readonly SchemaPermissionService $schemaPermissionService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('connections/Index', [
            'connections' => DatabaseConnection::query()
                ->latest()
                ->get([
                    'id',
                    'name',
                    'driver',
                    'host',
                    'port',
                    'database',
                    'username',
                    'ssl_enabled',
                    'is_active',
                    'max_rows',
                    'query_timeout_seconds',
                    'last_tested_at',
                    'created_at',
                ]),
        ]);
    }

    public function store(StoreDatabaseConnectionRequest $request): RedirectResponse
    {
        DatabaseConnection::create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conexión creada correctamente.')]);

        return back();
    }

    public function show(DatabaseConnection $connection): JsonResponse
    {
        return response()->json([
            'connection' => $connection->only([
                'id',
                'name',
                'driver',
                'host',
                'port',
                'database',
                'username',
                'ssl_enabled',
                'is_active',
                'max_rows',
                'query_timeout_seconds',
                'last_tested_at',
            ]),
        ]);
    }

    public function update(UpdateDatabaseConnectionRequest $request, DatabaseConnection $connection): RedirectResponse
    {
        $payload = $request->validated();

        if (! isset($payload['password']) || $payload['password'] === null || $payload['password'] === '') {
            unset($payload['password']);
        }

        $connection->update($payload);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conexión actualizada correctamente.')]);

        return back();
    }

    public function destroy(DatabaseConnection $connection): RedirectResponse
    {
        $connection->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conexión eliminada correctamente.')]);

        return back();
    }

    public function test(DatabaseConnection $connection): JsonResponse
    {
        $result = $this->connectionService->testConnection($connection);

        if ($result['success']) {
            $connection->forceFill(['last_tested_at' => now()])->save();
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function schemas(Request $request, DatabaseConnection $connection): JsonResponse
    {
        return response()->json([
            'schemas' => $this->schemaIntrospectionService->listSchemas($connection),
        ]);
    }

    public function tables(Request $request, DatabaseConnection $connection): JsonResponse
    {
        $tables = $this->schemaIntrospectionService->listTables($connection);
        $hasGlobalAccess = $request->user()?->hasPermission('connections.create') ?? false;
        $allowedTables = $this->schemaPermissionService->allowedTables($request->user(), $connection);

        if ($hasGlobalAccess) {
            return response()->json([
                'tables' => $tables,
            ]);
        }

        $filtered = collect($tables)
            ->filter(function (array $table) use ($allowedTables): bool {
                if ($allowedTables === []) {
                    return false;
                }

                return in_array((string) $table['name'], $allowedTables, true);
            })
            ->values();

        return response()->json([
            'tables' => $filtered,
        ]);
    }

    public function table(Request $request, DatabaseConnection $connection, string $table): JsonResponse
    {
        try {
            [, $tableName] = $this->schemaIntrospectionService->parseQualifiedTableIdentifier($table);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Invalid table.'], 422);
        }

        if (! $request->user()?->hasPermission('connections.create')
            && ! $this->schemaPermissionService->canAccessTable($request->user(), $connection, $tableName)) {
            return response()->json(['message' => 'This table is not allowed for this user.'], 403);
        }

        return response()->json([
            'columns' => $this->schemaIntrospectionService->describeTable($connection, $table),
        ]);
    }

    public function preview(Request $request, DatabaseConnection $connection, string $table): JsonResponse
    {
        try {
            [, $tableName] = $this->schemaIntrospectionService->parseQualifiedTableIdentifier($table);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Invalid table.'], 422);
        }

        $hasGlobalAccess = $request->user()?->hasPermission('connections.create') ?? false;

        if (! $hasGlobalAccess && ! $this->schemaPermissionService->canAccessTable($request->user(), $connection, $tableName)) {
            return response()->json(['message' => 'This table is not allowed for this user.'], 403);
        }

        return response()->json([
            'rows' => $this->schemaIntrospectionService->previewTable(
                $connection,
                $table,
                min($connection->max_rows, (int) config('monitorsql.max_rows', 1000))
            ),
        ]);
    }
}
