<?php

namespace App\Console\Commands;

use App\Models\AiMemoryProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('monitorsql:prune-ai-memory {--days=30 : Retention period in days}')]
#[Description('Prune stale AI memory profiles and old AI conversations/messages')]
class PruneAiMemory extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $messagesTable = (string) config('ai.conversations.tables.messages', 'agent_conversation_messages');
        $conversationsTable = (string) config('ai.conversations.tables.conversations', 'agent_conversations');

        $conversationIds = DB::table($conversationsTable)
            ->where('updated_at', '<', $cutoff)
            ->pluck('id')
            ->all();

        $deletedMessages = 0;
        $deletedConversations = 0;

        if ($conversationIds !== []) {
            $deletedMessages = DB::table($messagesTable)
                ->whereIn('conversation_id', $conversationIds)
                ->delete();

            $deletedConversations = DB::table($conversationsTable)
                ->whereIn('id', $conversationIds)
                ->delete();
        }

        $deletedProfiles = AiMemoryProfile::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where('last_used_at', '<', $cutoff)
                    ->orWhere(function ($nested) use ($cutoff): void {
                        $nested->whereNull('last_used_at')
                            ->where('updated_at', '<', $cutoff);
                    });
            })
            ->delete();

        $this->info(sprintf(
            'Pruned %d profiles, %d conversations, %d messages older than %d days.',
            $deletedProfiles,
            $deletedConversations,
            $deletedMessages,
            $days,
        ));

        return self::SUCCESS;
    }
}
