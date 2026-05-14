<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExportRequest;
use App\Models\DatabaseConnection;
use App\Models\DataExport;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\QueryValidationService;
use App\Services\SchemaPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly SchemaPermissionService $schemaPermissionService,
        private readonly QueryValidationService $queryValidationService,
        private readonly AuditService $auditService,
    ) {}

    public function store(StoreExportRequest $request): JsonResponse
    {
        $connection = DatabaseConnection::query()->findOrFail($request->integer('connection_id'));
        $hasGlobalAccess = $request->user()?->hasPermission('connections.create') ?? false;
        $allowedTables = $this->schemaPermissionService->allowedTables($request->user(), $connection);

        if (! $hasGlobalAccess && $allowedTables === []) {
            return response()->json(['message' => 'No table access configured for this user.'], 403);
        }

        $validation = $this->queryValidationService->validate(
            $request->string('sql')->toString(),
            min((int) config('monitorsql.export_max_rows', 10000), $connection->max_rows),
            $hasGlobalAccess ? [] : $allowedTables,
        );

        if (! $validation['is_valid']) {
            return response()->json([
                'message' => 'SQL validation failed.',
                'errors' => $validation['errors'],
            ], 422);
        }

        $export = $this->exportService->queueExport(
            user: $request->user(),
            connection: $connection,
            sql: $validation['sql_with_limit'],
            format: $request->string('format')->toString(),
        );

        $this->auditService->record(
            action: 'export.queued',
            user: $request->user(),
            connection: $connection,
            sql: $validation['sql_with_limit'],
            status: 'success',
            request: $request,
            metadata: ['format' => $request->string('format')->toString(), 'export_id' => $export->id],
        );

        return response()->json([
            'export' => $export,
            'message' => 'Export queued successfully.',
        ], 202);
    }

    public function index(Request $request): JsonResponse
    {
        $exports = DataExport::query()
            ->where('user_id', $request->user()?->id)
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('format'), function ($query) use ($request): void {
                $query->where('format', $request->string('format')->toString());
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
            })
            ->latest()
            ->paginate(15);

        return response()->json($exports);
    }

    public function download(Request $request, DataExport $export): StreamedResponse|JsonResponse
    {
        if ($export->user_id !== $request->user()?->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($export->status !== 'completed' || $export->file_path === null) {
            return response()->json(['message' => 'Export is not ready.'], 422);
        }

        if ($export->expires_at !== null && $export->expires_at->isPast()) {
            return response()->json(['message' => 'Export expired.'], 410);
        }

        if (! Storage::disk('local')->exists($export->file_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $export->forceFill(['downloaded_at' => now()])->save();

        $this->auditService->record(
            action: 'export.downloaded',
            user: $request->user(),
            connection: $export->connection,
            sql: $export->sql,
            status: 'success',
            request: $request,
            metadata: ['export_id' => $export->id, 'format' => $export->format],
        );

        return Storage::disk('local')->download(
            $export->file_path,
            sprintf('monitorsql_export_%d.%s', $export->id, $export->format),
        );
    }
}
