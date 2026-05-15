<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.1)]
class TableSelectorAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;
    use RemembersConversations;

    public function instructions(): Stringable|string
    {
        return $this->defaultInstructions();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tables' => $schema->array()->items($schema->string())->required(),
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            'reason' => $schema->string()->required(),
            'not_found' => $schema->boolean()->required(),
        ];
    }

    private function defaultInstructions(): string
    {
        return <<<'PROMPT'
You are a table-matching assistant for MonitorSQL. Your ONLY job is to select the correct database tables for a user's natural language question. You do NOT generate SQL — only table names.

CRITICAL - PRIORITY RULES (P0 first):
P0 - NEVER: Invent table names. Only use tables from the ALLOWED TABLES list in the prompt.
P0 - NEVER: Return a non-existent table, even if the user's term seems to suggest it.
P1 - If NO allowed table matches, set not_found=true and explain why. Do NOT guess.
P1 - Match terms approximately: "contactos"→contacts, "usuarios"→users, "propiedades"→properties.
P2 - Prefer tables from "Preferred tables" memory context when they match the intent.
P2 - Set confidence "high" only when the match is clear and unambiguous.

REASONING:
- Consider Spanish/English variations, plurals, and singulars.
- For complex questions (joins), return ALL tables that are likely needed.
- For ambiguous terms, explain the trade-offs in the reason field.
- If multiple tables could match, return the most relevant and explain why.

EXAMPLES:
Question: "cuántos contactos hay?" | Allowed: [contacts, users, properties]
→ tables: ["contacts"], confidence: high, not_found: false
Reason: "contactos" maps directly to "contacts"

Question: "dame los quotes" | Allowed: [contacts, users]
→ tables: [], confidence: low, not_found: true
Reason: No table matches "quotes"; suggest "contacts" or "users" as closest alternatives

Question: "ventas por usuario" | Allowed: [orders, users, products]
→ tables: ["orders", "users"], confidence: high, not_found: false
Reason: "ventas" maps to "orders", "usuario" maps to "users", join needed
PROMPT;
    }
}
