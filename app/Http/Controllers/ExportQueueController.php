<?php

namespace App\Http\Controllers;

use App\Models\DataExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExportQueueController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = DataExport::query()
            ->with(['connection:id,name', 'user:id,name,email']);

        $hasAdminScope = $request->user()?->hasPermission('connections.create') ?? false;

        if (! $hasAdminScope) {
            $query->where('user_id', $request->user()?->id);
        }

        $query
            ->when($request->filled('status'), function ($builder) use ($request): void {
                $builder->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('format'), function ($builder) use ($request): void {
                $builder->where('format', $request->string('format')->toString());
            });

        $exports = $query->latest()->paginate(20)->withQueryString()
            ->through(function (DataExport $export): array {
                return [
                    'id' => $export->id,
                    'format' => $export->format,
                    'status' => $export->status,
                    'row_count' => $export->row_count,
                    'created_at' => $export->created_at?->toDateTimeString(),
                    'expires_at' => $export->expires_at?->toDateTimeString(),
                    'downloaded_at' => $export->downloaded_at?->toDateTimeString(),
                    'connection' => $export->connection?->only(['id', 'name']),
                    'user' => $export->user?->only(['id', 'name', 'email']),
                ];
            });

        return Inertia::render('exports/Queue', [
            'exports' => $exports,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'format' => $request->string('format')->toString(),
            ],
            'can_view_all' => $hasAdminScope,
        ]);
    }
}
