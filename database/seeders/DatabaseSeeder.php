<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemPrompt;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissionsByRole = [
            'admin' => [
                'connections.view',
                'connections.create',
                'connections.update',
                'connections.delete',
                'schemas.view',
                'tables.view',
                'queries.execute',
                'queries.ai_generate',
                'queries.export',
                'audit.view',
            ],
            'analyst' => [
                'connections.view',
                'schemas.view',
                'tables.view',
                'queries.execute',
                'queries.ai_generate',
                'queries.export',
            ],
            'business_user' => [
                'connections.view',
                'tables.view',
                'queries.ai_generate',
                'queries.execute',
                'queries.export',
            ],
            'auditor' => [
                'audit.view',
            ],
        ];

        $allPermissionKeys = collect($permissionsByRole)
            ->flatten()
            ->unique()
            ->values();

        $permissions = $allPermissionKeys
            ->mapWithKeys(function (string $permissionKey): array {
                $permission = Permission::query()->firstOrCreate(
                    ['key' => $permissionKey],
                    ['name' => str_replace('.', ' ', $permissionKey)],
                );

                return [$permissionKey => $permission];
            });

        foreach ($permissionsByRole as $roleKey => $permissionKeys) {
            $role = Role::query()->firstOrCreate(
                ['key' => $roleKey],
                ['name' => str_replace('_', ' ', $roleKey)],
            );

            $role->permissions()->sync(
                collect($permissionKeys)
                    ->map(fn (string $permissionKey): int => $permissions[$permissionKey]->id)
                    ->all(),
            );
        }

        $adminUser = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => 'password'],
        );

        $analystUser = User::query()->firstOrCreate(
            ['email' => 'analyst@example.com'],
            ['name' => 'Analyst User', 'password' => 'password'],
        );

        $businessUser = User::query()->firstOrCreate(
            ['email' => 'business@example.com'],
            ['name' => 'Business User', 'password' => 'password'],
        );

        $auditorUser = User::query()->firstOrCreate(
            ['email' => 'auditor@example.com'],
            ['name' => 'Auditor User', 'password' => 'password'],
        );

        $adminUser->roles()->sync([Role::query()->where('key', 'admin')->value('id')]);
        $analystUser->roles()->sync([Role::query()->where('key', 'analyst')->value('id')]);
        $businessUser->roles()->sync([Role::query()->where('key', 'business_user')->value('id')]);
        $auditorUser->roles()->sync([Role::query()->where('key', 'auditor')->value('id')]);

        SystemPrompt::query()->firstOrCreate(
            ['key' => 'sql_assistant'],
            [
                'content' => <<<'PROMPT'
You are a SQL read-only assistant for MonitorSQL. You generate safe, read-only SQL queries based on natural language questions.

MANDATORY RULES:
- Generate only SELECT statements or WITH CTE statements that end in SELECT.
- Never generate INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, CREATE, REPLACE, GRANT, REVOKE, EXEC, EXECUTE, CALL, MERGE, or UPSERT.
- Use only tables available in the provided context. Never invent tables or columns.
- Add LIMIT to row-level queries.
- Return only valid structured output.

REASONING RULES - APPLY THESE BEFORE GENERATING SQL:
- Understand the user's INTENT first: count, list, filter, compare, group, summarize, or explore.
- Perform FUZZY MATCHING between user terms and actual table names:
  * "contactos" → contacts
  * "usuarios" → users
  * "propiedades" → properties
  * "ofertas" → pending_offers or offer_history (choose the most relevant)
  * "negociaciones" → negotiations
  * "notificaciones" → buyer_notification_preferences
  * "imágenes"/"fotos" → property_images
  * "guardados"/"favoritos" → property_saved
  * "registros"/"datos"/"registrados" → COUNT(*) or SELECT *
  * "historial" → offer_history or activity_logs
  * "contenido"/"cms" → cms_contents
- Consider plurals, singulars, and Spanish/English variations.
- If the user's term is ambiguous, pick the MOST RELEVANT table and EXPLAIN your choice in the explanation.
- If NO table matches the user's intent, suggest the closest alternatives from the allowed tables list.
- For terms like "últimos", "recientes", sort by date columns if available (created_at, updated_at).
- For "top N", "mayores", "mejores" use ORDER BY with appropriate column + LIMIT.
- Keep explanations concise and in Spanish when the user writes in Spanish.
PROMPT,
                'description' => 'SQL query assistant base system prompt.',
            ],
        );
    }
}
