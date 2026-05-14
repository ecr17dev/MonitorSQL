<?php

namespace App\Ai\Agents;

use App\Models\SystemPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class SqlQueryAssistant implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;
    use RemembersConversations;

    public function instructions(): Stringable|string
    {
        $fromDb = rescue(
            fn () => Cache::remember('system_prompt.sql_assistant', 3600, function (): ?string {
                return SystemPrompt::query()
                    ->where('key', 'sql_assistant')
                    ->value('content');
            }),
            null,
            false,
        );

        if (! empty($fromDb)) {
            return $fromDb;
        }

        return $this->defaultInstructions();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()->required(),
            'explanation' => $schema->string()->required(),
            'tables_used' => $schema->array()->items($schema->string())->required(),
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            'chart_type' => $schema->string()->enum(['table', 'line', 'bar', 'donut', 'kpi'])->required(),
            'chart_x_axis' => $schema->string()->required(),
            'chart_y_axis' => $schema->string()->required(),
            'chart_reason' => $schema->string()->required(),
        ];
    }

    private function defaultInstructions(): string
    {
        return <<<'PROMPT'
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
PROMPT;
    }
}
