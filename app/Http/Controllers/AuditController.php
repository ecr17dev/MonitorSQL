<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()?->hasPermission('audit.view')) {
            abort(403);
        }

        $query = AuditLog::query()
            ->with(['user:id,name,email', 'connection:id,name']);

        $hasAdminScope = $request->user()?->hasPermission('connections.create') ?? false;

        if (! $hasAdminScope) {
            $query->where('user_id', $request->user()?->id);
        }

        $query
            ->when($request->filled('action'), function ($builder) use ($request): void {
                $builder->where('action', $request->string('action')->toString());
            })
            ->when($request->filled('status'), function ($builder) use ($request): void {
                $builder->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('user_id'), function ($builder) use ($request, $hasAdminScope): void {
                if ($hasAdminScope) {
                    $builder->where('user_id', $request->integer('user_id'));
                }
            })
            ->when($request->filled('connection_id'), function ($builder) use ($request): void {
                $builder->where('connection_id', $request->integer('connection_id'));
            })
            ->when($request->filled('date_from'), function ($builder) use ($request): void {
                $builder->whereDate('created_at', '>=', $request->string('date_from')->toString());
            })
            ->when($request->filled('date_to'), function ($builder) use ($request): void {
                $builder->whereDate('created_at', '<=', $request->string('date_to')->toString());
            });

        $logs = $query->latest()->paginate(20)->withQueryString()
            ->through(function (AuditLog $log): array {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'status' => $log->status,
                    'sql_preview' => $log->sql_preview,
                    'duration_ms' => $log->duration_ms,
                    'rows_returned' => $log->rows_returned,
                    'ip_address' => $log->ip_address,
                    'metadata' => $log->metadata,
                    'created_at' => $log->created_at?->toDateTimeString(),
                    'user' => $log->user?->only(['id', 'name', 'email']),
                    'connection' => $log->connection?->only(['id', 'name']),
                ];
            });

        return Inertia::render('audit/Index', [
            'logs' => $logs,
            'filters' => [
                'action' => $request->string('action')->toString(),
                'status' => $request->string('status')->toString(),
                'user_id' => $request->string('user_id')->toString(),
                'connection_id' => $request->string('connection_id')->toString(),
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
            ],
            'can_view_all' => $hasAdminScope,
        ]);
    }
}
