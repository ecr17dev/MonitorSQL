<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AuditUser = {
    id: number;
    name: string;
    email: string;
};

type AuditConnection = {
    id: number;
    name: string;
};

type AuditItem = {
    id: number;
    action: string;
    status: string;
    sql_preview: string | null;
    duration_ms: number | null;
    rows_returned: number | null;
    ip_address: string;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
    user: AuditUser | null;
    connection: AuditConnection | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedLogs = {
    data: AuditItem[];
    links: PaginationLink[];
    total: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Auditoría', href: '/audit' },
        ],
    },
});

const props = defineProps<{
    logs: PaginatedLogs;
    filters: {
        action: string;
        status: string;
        user_id: string;
        connection_id: string;
        date_from: string;
        date_to: string;
    };
    can_view_all: boolean;
}>();

const form = reactive({
    action: props.filters.action,
    status: props.filters.status,
    user_id: props.filters.user_id,
    connection_id: props.filters.connection_id,
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
});

const actionLabels: Record<string, string> = {
    'query.executed': 'Consulta ejecutada',
    'query.ai.generated': 'IA generó SQL',
    'query.blocked': 'Consulta bloqueada',
    'query.validated': 'Consulta validada',
    'query.ai.blocked': 'IA bloqueada',
    'query.failed': 'Consulta fallida',
};

type BadgeVariant = 'default' | 'outline' | 'destructive' | 'secondary';

const statusVariants: Record<string, BadgeVariant> = {
    success: 'default',
    failed: 'destructive',
    blocked: 'secondary',
};

function applyFilters(): void {
    router.get('/audit', form, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function clearFilters(): void {
    form.action = '';
    form.status = '';
    form.user_id = '';
    form.connection_id = '';
    form.date_from = '';
    form.date_to = '';
    applyFilters();
}
</script>

<template>
    <Head title="Auditoría" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card>
            <CardHeader>
                <CardTitle>Auditoría</CardTitle>
                <CardDescription>Registro de todas las acciones ejecutadas en la plataforma.</CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap items-center gap-2 text-sm">
                <Badge variant="secondary">{{ props.logs.total }} registros</Badge>
                <p class="text-muted-foreground">
                    {{ props.can_view_all ? 'Vista global por permisos de administrador.' : 'Vista limitada a tus acciones.' }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Filtros</CardTitle>
                <CardDescription>Refina por acción, estado, fechas y más.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="action">Acción</Label>
                    <Input id="action" v-model="form.action" placeholder="query.executed" />
                </div>
                <div class="space-y-2">
                    <Label for="status">Estado</Label>
                    <Input id="status" v-model="form.status" placeholder="success/failed/blocked" />
                </div>
                <div class="space-y-2" v-if="props.can_view_all">
                    <Label for="user_id">ID Usuario</Label>
                    <Input id="user_id" v-model="form.user_id" placeholder="ID de usuario" />
                </div>
                <div class="space-y-2">
                    <Label for="connection_id">ID Conexión</Label>
                    <Input id="connection_id" v-model="form.connection_id" placeholder="ID de conexión" />
                </div>
                <div class="space-y-2">
                    <Label for="date_from">Desde</Label>
                    <Input id="date_from" v-model="form.date_from" placeholder="YYYY-MM-DD" />
                </div>
                <div class="space-y-2">
                    <Label for="date_to">Hasta</Label>
                    <Input id="date_to" v-model="form.date_to" placeholder="YYYY-MM-DD" />
                </div>
                <div class="flex items-end gap-2">
                    <Button @click="applyFilters">Aplicar</Button>
                    <Button variant="secondary" @click="clearFilters">Limpiar</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Registros</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="p-2">ID</th>
                                <th class="p-2">Acción</th>
                                <th class="p-2">Estado</th>
                                <th class="p-2">Usuario</th>
                                <th class="p-2">Conexión</th>
                                <th class="p-2">SQL</th>
                                <th class="p-2">Duración</th>
                                <th class="p-2">IP</th>
                                <th class="p-2">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in props.logs.data" :key="item.id" class="border-b">
                                <td class="p-2">#{{ item.id }}</td>
                                <td class="p-2 text-xs">{{ actionLabels[item.action] ?? item.action }}</td>
                                <td class="p-2">
                                    <Badge :variant="statusVariants[item.status] ?? 'outline'">{{ item.status }}</Badge>
                                </td>
                                <td class="p-2 text-xs">{{ item.user?.email ?? 'N/A' }}</td>
                                <td class="p-2 text-xs">{{ item.connection?.name ?? 'N/A' }}</td>
                                <td class="max-w-[200px] truncate p-2 text-xs font-mono">{{ item.sql_preview ?? '-' }}</td>
                                <td class="p-2 text-xs">{{ item.duration_ms !== null ? item.duration_ms + ' ms' : '-' }}</td>
                                <td class="p-2 text-xs">{{ item.ip_address }}</td>
                                <td class="p-2 text-xs">{{ item.created_at ?? 'N/A' }}</td>
                            </tr>
                            <tr v-if="props.logs.data.length === 0">
                                <td colspan="9" class="p-4 text-center text-muted-foreground">
                                    No se encontraron registros de auditoría.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        v-for="(link, index) in props.logs.links"
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
