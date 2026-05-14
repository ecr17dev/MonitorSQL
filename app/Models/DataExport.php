<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'connection_id',
    'query_run_id',
    'format',
    'status',
    'sql',
    'file_path',
    'row_count',
    'expires_at',
    'downloaded_at',
    'meta',
])]
class DataExport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<DatabaseConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class, 'connection_id');
    }

    /**
     * @return BelongsTo<QueryRun, $this>
     */
    public function queryRun(): BelongsTo
    {
        return $this->belongsTo(QueryRun::class);
    }
}
