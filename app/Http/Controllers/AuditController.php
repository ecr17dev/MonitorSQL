<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->hasPermission('audit.view')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $logs = AuditLog::query()
            ->with(['user:id,name,email', 'connection:id,name'])
            ->when($request->filled('action'), function ($query) use ($request): void {
                $query->where('action', $request->string('action')->toString());
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('user_id'), function ($query) use ($request): void {
                $query->where('user_id', $request->integer('user_id'));
            })
            ->when($request->filled('connection_id'), function ($query) use ($request): void {
                $query->where('connection_id', $request->integer('connection_id'));
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
            })
            ->latest()
            ->paginate(20);

        return response()->json($logs);
    }
}
