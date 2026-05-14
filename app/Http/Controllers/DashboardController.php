<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DatabaseConnection;
use App\Models\QueryRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'connections' => DatabaseConnection::query()
                ->select(['id', 'name', 'driver', 'host', 'database', 'is_active', 'max_rows'])
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'recentQueryRuns' => QueryRun::query()
                ->where('user_id', $user?->id)
                ->latest()
                ->limit(10)
                ->get(['id', 'status', 'rows_returned', 'duration_ms', 'created_at', 'sql']),
            'recentAudits' => AuditLog::query()
                ->where('user_id', $user?->id)
                ->latest()
                ->limit(10)
                ->get(['id', 'action', 'status', 'created_at']),
        ]);
    }
}
