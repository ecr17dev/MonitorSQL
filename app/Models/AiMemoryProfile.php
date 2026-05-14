<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'connection_id',
    'preferred_tables',
    'term_aliases',
    'successful_query_patterns',
    'last_used_at',
])]
class AiMemoryProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_tables' => 'array',
            'term_aliases' => 'array',
            'successful_query_patterns' => 'array',
            'last_used_at' => 'datetime',
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
}
