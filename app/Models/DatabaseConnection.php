<?php

namespace App\Models;

use Database\Factories\DatabaseConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'driver',
    'host',
    'port',
    'database',
    'username',
    'password',
    'ssl_enabled',
    'options',
    'is_active',
    'max_rows',
    'query_timeout_seconds',
    'last_tested_at',
    'created_by',
])]
class DatabaseConnection extends Model
{
    /** @use HasFactory<DatabaseConnectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'options' => 'array',
            'ssl_enabled' => 'boolean',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ConnectionPermission, $this>
     */
    public function scopedPermissions(): HasMany
    {
        return $this->hasMany(ConnectionPermission::class, 'connection_id');
    }
}
