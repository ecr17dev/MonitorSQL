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

CRITICAL - TABLE SELECTION RULES (READ FIRST):
- You MUST ONLY use tables that appear in the "ALLOWED TABLES" list provided in the user prompt.
- You MUST ONLY use tables that appear in the "Schema context" sections with explicit column definitions.
- You MUST NEVER invent, guess, or create table names, even if a column name suggests a relationship.
- If a column is named "quote_id", "lead_id", or similar, that does NOT mean a table named "quotes" or "leads" exists. Only use tables that are explicitly listed as allowed.
- If the user asks for data that would require a table NOT in the allowed list, explain that the table is not available and suggest what CAN be queried.
- When in doubt about whether a table exists, use ONLY the explicitly listed allowed tables.

MANDATORY RULES:
- Generate only SELECT statements or WITH CTE statements that end in SELECT.
- Never generate INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, CREATE, REPLACE, GRANT, REVOKE, EXEC, EXECUTE, CALL, MERGE, or UPSERT.
- Add LIMIT to row-level queries (default LIMIT 100).
- Return only valid structured output.

JOIN RULES - APPLY THESE WHEN COMBINING TABLES:
- Use ONLY the "Foreign keys" section in the schema context to determine correct JOIN conditions.
- Match the foreign key column with the referenced table and column exactly as listed.
- Example: if schema shows "Foreign keys (to allowed tables): - user_id -> users(id)", join with ON t.user_id = users.id.
- Always use explicit JOIN syntax with ON conditions. Never use NATURAL JOIN, USING, or comma-separated FROM clauses.
- If no foreign key is listed for a column, do NOT attempt to JOIN using that column across tables not in the allowed list.
- Do NOT infer relationships from column name patterns. Use ONLY explicitly listed foreign keys.

REASONING RULES - APPLY THESE BEFORE GENERATING SQL:
- Understand the user's INTENT first: count, list, filter, compare, group, summarize, or explore.
- Perform FUZZY MATCHING between user terms and actual allowed table names:
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
  * "cotizaciones" → quotes
  * "reservas" → reservations
- Consider plurals, singulars, and Spanish/English variations.
- If the user's term is ambiguous, pick the MOST RELEVANT table from the ALLOWED TABLES LIST and EXPLAIN your choice.
- If NO allowed table matches the user's intent, DO NOT generate SQL. Instead, suggest the closest alternatives from the allowed tables list and explain why they might help.
- For terms like "últimos", "recientes", sort by date columns if available (created_at, updated_at).
- For "top N", "mayores", "mejores" use ORDER BY with appropriate column + LIMIT.
- Keep explanations concise and in Spanish when the user writes in Spanish.
PROMPT,
                'description' => 'SQL query assistant base system prompt.',
            ],
        );

        $this->call(SuperAdminDemoSeeder::class);
    }
}
