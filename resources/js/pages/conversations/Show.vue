<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { toastActionError, toastActionWarning } from '@/lib/actionToast';

type ConversationDetail = {
    id: string;
    title: string;
    is_archived: boolean;
    is_pinned: boolean;
    message_count: number;
    connection_id: number | null;
    last_message_at: string | null;
    created_at: string | null;
};

type MessageItem = {
    id: string;
    role: string;
    content: string;
    agent: string;
    usage: Record<string, unknown> | null;
    tool_calls: unknown[] | null;
    created_at: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Conversaciones IA', href: '/conversations' },
            { title: 'Detalle', href: '' },
        ],
    },
});

const props = defineProps<{
    conversation: ConversationDetail;
    messages: MessageItem[];
}>();

const chatUrl = '/chat';

function deleteConversation(): void {
    if (!confirm('¿Eliminar esta conversación y todos sus mensajes?')) {
        toastActionWarning('Eliminación cancelada.');
        return;
    }

    router.delete(`/conversations/${props.conversation.id}`, {
        onSuccess: () => {
            router.visit('/conversations');
        },
        onError: (errors) => {
            toastActionError(errors, 'No se pudo eliminar la conversación.');
        },
    });
}

function toggleArchive(): void {
    router.put(`/conversations/${props.conversation.id}`, {
        is_archived: !props.conversation.is_archived,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo actualizar la conversación.');
        },
    });
}

function togglePin(): void {
    router.put(`/conversations/${props.conversation.id}`, {
        is_pinned: !props.conversation.is_pinned,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo actualizar la conversación.');
        },
    });
}
</script>

<template>
    <Head :title="props.conversation.title" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold">{{ props.conversation.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ props.messages.length }} mensajes ·
                    {{ props.conversation.created_at }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button size="sm" variant="outline" @click="togglePin">
                    {{ props.conversation.is_pinned ? 'Desfijar' : 'Fijar' }}
                </Button>
                <Button size="sm" variant="outline" @click="toggleArchive">
                    {{ props.conversation.is_archived ? 'Desarchivar' : 'Archivar' }}
                </Button>
                <Button size="sm" variant="outline" as-child>
                    <a :href="chatUrl">Nuevo chat</a>
                </Button>
                <Button size="sm" variant="destructive" @click="deleteConversation">Eliminar</Button>
            </div>
        </div>

        <div class="grid gap-2">
            <div
                v-for="message in props.messages"
                :key="message.id"
                class="rounded-md border p-4"
                :class="message.role === 'assistant' ? 'bg-muted/50 ml-0' : 'ml-4 border-primary/30'"
            >
                <div class="mb-1 flex items-center gap-2">
                    <Badge :variant="message.role === 'user' ? 'default' : 'secondary'">
                        {{ message.role === 'user' ? 'Tú' : 'MonitorSQL' }}
                    </Badge>
                    <span class="text-xs text-muted-foreground">{{ message.created_at }}</span>
                    <Badge v-if="message.usage" variant="outline" class="text-xs">
                        {{ (message.usage as Record<string, unknown>).total_tokens ?? '?' }} tokens
                    </Badge>
                </div>
                <p class="whitespace-pre-wrap text-sm">{{ message.content }}</p>
            </div>

            <div v-if="props.messages.length === 0" class="p-4 text-center text-muted-foreground">
                Esta conversación no tiene mensajes.
            </div>
        </div>
    </div>
</template>
