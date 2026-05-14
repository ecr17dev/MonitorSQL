<?php

namespace App\Services;

use App\Models\ConnectionPermission;
use App\Models\DatabaseConnection;
use App\Models\User;

class SchemaPermissionService
{
    /**
     * @return array<int, string>
     */
    public function allowedTables(User $user, DatabaseConnection $connection): array
    {
        $roleIds = $user->roles()->pluck('roles.id')->all();

        $allowed = ConnectionPermission::query()
            ->where('connection_id', $connection->id)
            ->where('can_view', true)
            ->where(function ($query) use ($user, $roleIds): void {
                $query->where('user_id', $user->id);

                if ($roleIds !== []) {
                    $query->orWhereIn('role_id', $roleIds);
                }
            })
            ->whereNotNull('table_name')
            ->pluck('table_name')
            ->map(fn (string $table): string => trim($table))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return is_array($allowed) ? $allowed : [];
    }

    public function canAccessTable(User $user, DatabaseConnection $connection, string $table): bool
    {
        $allowedTables = $this->allowedTables($user, $connection);

        if ($allowedTables === []) {
            return false;
        }

        if (in_array($table, $allowedTables, true)) {
            return true;
        }

        $tableWithoutSchema = str_contains($table, '.')
            ? (string) str($table)->afterLast('.')
            : $table;

        return in_array($tableWithoutSchema, $allowedTables, true);
    }
}
