<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type QueueUser = {
    id: number;
    name: string;
    email: string;
};

type QueueConnection = {
    id: number;
    name: string;
};

type QueueItem = {
    id: number;
    format: string;
    status: string;
    row_count: number;
    created_at: string | null;
    expires_at: string | null;
    downloaded_at: string | null;
    user: QueueUser | null;
    connection: QueueConnection | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedQueue = {
    data: QueueItem[];
    links: PaginationLink[];
    total: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Cola de exportaciones',
                href: '/exports/queue',
            },
        ],
    },
});

const props = defineProps<{
    exports: PaginatedQueue;
    filters: {
        status: string;
        format: string;
    };
    can_view_all: boolean;
}>();

const form = reactive({
    status: props.filters.status,
    format: props.filters.format,
});

function applyFilters(): void {
    router.get(
        '/exports/queue',
        {
            status: form.status,
            format: form.format,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function clearFilters(): void {
    form.status = '';
    form.format = '';
    applyFilters();
}
</script>

<template>
    <Head title="Cola de exportaciones" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card>
            <CardHeader>
                <CardTitle>Cola de exportaciones</CardTitle>
                <CardDescription>Seguimiento de estados y descargas autorizadas.</CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap items-center gap-2 text-sm">
                <Badge variant="secondary">{{ props.exports.total }} exportaciones</Badge>
                <p class="text-muted-foreground">
                    {{ props.can_view_all ? 'Vista global por permisos de administrador.' : 'Vista limitada a tus exportaciones.' }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Filtros</CardTitle>
                <CardDescription>Refina por estado y formato.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-4">
                <div class="space-y-2">
                    <Label for="status">Estado</Label>
                    <Input id="status" v-model="form.status" placeholder="pending/completed/failed/expired" />
                </div>
                <div class="space-y-2">
                    <Label for="format">Formato</Label>
                    <Input id="format" v-model="form.format" placeholder="csv/xlsx/json" />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <Button @click="applyFilters">Aplicar</Button>
                    <Button variant="secondary" @click="clearFilters">Limpiar</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Exportaciones</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="p-2">ID</th>
                                <th class="p-2">Estado</th>
                                <th class="p-2">Formato</th>
                                <th class="p-2">Filas</th>
                                <th class="p-2">Conexión</th>
                                <th class="p-2">Usuario</th>
                                <th class="p-2">Creado</th>
                                <th class="p-2">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in props.exports.data" :key="item.id" class="border-b">
                                <td class="p-2">#{{ item.id }}</td>
                                <td class="p-2">
                                    <Badge variant="outline">{{ item.status }}</Badge>
                                </td>
                                <td class="p-2">{{ item.format.toUpperCase() }}</td>
                                <td class="p-2">{{ item.row_count }}</td>
                                <td class="p-2">{{ item.connection?.name ?? 'N/A' }}</td>
                                <td class="p-2">{{ item.user?.email ?? 'N/A' }}</td>
                                <td class="p-2">{{ item.created_at ?? 'N/A' }}</td>
                                <td class="p-2">
                                    <Button
                                        v-if="item.status === 'completed'"
                                        size="sm"
                                        variant="secondary"
                                        as-child
                                    >
                                        <a :href="`/exports/${item.id}/download`">Descargar</a>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        v-for="(link, index) in props.exports.links"
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
