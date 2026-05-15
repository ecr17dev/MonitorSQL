<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toastActionError, toastActionWarning } from '@/lib/actionToast';

type ConversationItem = {
    id: string;
    title: string;
    is_archived: boolean;
    is_pinned: boolean;
    message_count: number;
    last_message_at: string | null;
    connection_id: number | null;
    created_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedConversations = {
    data: ConversationItem[];
    links: PaginationLink[];
    total: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Conversaciones IA', href: '/conversations' },
        ],
    },
});

const props = defineProps<{
    conversations: PaginatedConversations;
    filters: {
        search: string;
        connection_id: string;
        show_archived: string;
    };
}>();

const form = reactive({
    search: props.filters.search,
    connection_id: props.filters.connection_id,
    show_archived: props.filters.show_archived === '1',
});

function applyFilters(): void {
    router.get('/conversations', {
        search: form.search,
        connection_id: form.connection_id,
        show_archived: form.show_archived ? '1' : '',
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function clearFilters(): void {
    form.search = '';
    form.connection_id = '';
    form.show_archived = false;
    applyFilters();
}

function togglePin(id: string, current: boolean): void {
    router.put(`/conversations/${id}`, {
        is_pinned: !current,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo actualizar la conversación.');
        },
    });
}

function toggleArchive(id: string, current: boolean): void {
    router.put(`/conversations/${id}`, {
        is_archived: !current,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo actualizar la conversación.');
        },
    });
}

function deleteConversation(id: string): void {
    if (!confirm('¿Eliminar esta conversación y todos sus mensajes?')) {
        toastActionWarning('Eliminación cancelada.');
        return;
    }

    router.delete(`/conversations/${id}`, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo eliminar la conversación.');
        },
    });
}
</script>

<template>
    <Head title="Conversaciones IA" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card>
            <CardHeader>
                <CardTitle>Conversaciones IA</CardTitle>
                <CardDescription>Historial de conversaciones con la IA. Archiva, fija o elimina.</CardDescription>
            </CardHeader>
            <CardContent class="text-sm">
                <Badge variant="secondary">{{ props.conversations.total }} conversaciones</Badge>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Filtros</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-4">
                <div class="space-y-2">
                    <Label for="search">Búsqueda</Label>
                    <Input id="search" v-model="form.search" placeholder="Buscar por título..." />
                </div>
                <div class="flex items-end gap-2">
                    <Button @click="applyFilters">Aplicar</Button>
                    <Button variant="secondary" @click="clearFilters">Limpiar</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Conversaciones</CardTitle>
                    <div class="flex items-center gap-2">
                        <Label for="show_archived" class="text-sm">Mostrar archivadas</Label>
                        <input type="checkbox" id="show_archived" v-model="form.show_archived" @change="applyFilters" />
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3">
                    <Card
                        v-for="conversation in props.conversations.data"
                        :key="conversation.id"
                        class="cursor-pointer"
                        :class="{ 'border-primary/50': conversation.is_pinned }"
                    >
                        <CardContent class="flex items-center justify-between p-4">
                            <div class="grid gap-1">
                                <div class="flex items-center gap-2">
                                    <span v-if="conversation.is_pinned" class="text-yellow-500">📌</span>
                                    <a :href="`/conversations/${conversation.id}`" class="font-medium hover:underline">
                                        {{ conversation.title }}
                                    </a>
                                </div>
                                <div class="flex gap-2 text-xs text-muted-foreground">
                                    <span>{{ conversation.message_count }} mensajes</span>
                                    <span v-if="conversation.last_message_at">· {{ conversation.last_message_at }}</span>
                                    <Badge v-if="conversation.is_archived" variant="secondary" class="text-xs">Archivada</Badge>
                                </div>
                            </div>
                            <div class="flex gap-1">
                                <Button size="sm" variant="ghost" @click="togglePin(conversation.id, conversation.is_pinned)">
                                    {{ conversation.is_pinned ? 'Desfijar' : 'Fijar' }}
                                </Button>
                                <Button size="sm" variant="ghost" @click="toggleArchive(conversation.id, conversation.is_archived)">
                                    {{ conversation.is_archived ? 'Desarchivar' : 'Archivar' }}
                                </Button>
                                <Button size="sm" variant="ghost" @click="deleteConversation(conversation.id)">Eliminar</Button>
                            </div>
                        </CardContent>
                    </Card>

                    <div v-if="props.conversations.data.length === 0" class="p-4 text-center text-muted-foreground">
                        No hay conversaciones.
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        v-for="(link, index) in props.conversations.links"
                        :key="index"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="link.url === null"
                        @click="link.url ? router.visit(link.url, { preserveScroll: true, preserveState: true }) : null"
                        v-html="link.label"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
