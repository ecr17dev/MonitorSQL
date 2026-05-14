<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?User $user,
        ?DatabaseConnection $connection,
        ?string $sql,
        string $status,
        ?Request $request = null,
        ?int $durationMs = null,
        ?int $rowsReturned = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'connection_id' => $connection?->id,
            'action' => $action,
            'sql_hash' => $sql !== null ? hash('sha256', $sql) : null,
            'sql_preview' => $sql !== null ? Str::limit($sql, 500) : null,
            'status' => $status,
            'duration_ms' => $durationMs,
            'rows_returned' => $rowsReturned,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
