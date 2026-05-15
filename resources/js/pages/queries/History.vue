<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toastActionError, toastActionWarning } from '@/lib/actionToast';

type RunConnection = {
    id: number;
    name: string;
};

type RunItem = {
    id: number;
    status: string;
    category: string | null;
    tags: string[] | null;
    is_favorite: boolean;
    duration_ms: number;
    rows_returned: number;
    is_ai_generated: boolean;
    sql: string;
    created_at: string | null;
    connection: RunConnection | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedRuns = {
    data: RunItem[];
    links: PaginationLink[];
    total: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Historial de consultas', href: '/queries/history' },
        ],
    },
});

const props = defineProps<{
    runs: PaginatedRuns;
    filters: {
        status: string;
        category: string;
        connection_id: string;
        search: string;
        is_favorite: string;
        date_from: string;
        date_to: string;
    };
    categories: string[];
    statuses: string[];
}>();

const form = reactive({
    status: props.filters.status,
    category: props.filters.category,
    connection_id: props.filters.connection_id,
    search: props.filters.search,
    is_favorite: props.filters.is_favorite,
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
});

function applyFilters(): void {
    router.get('/queries/history', form, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function clearFilters(): void {
    form.status = '';
    form.category = '';
    form.connection_id = '';
    form.search = '';
    form.is_favorite = '';
    form.date_from = '';
    form.date_to = '';
    applyFilters();
}

function deleteRun(id: number): void {
    if (!confirm('¿Eliminar esta consulta del historial?')) {
        toastActionWarning('Eliminación cancelada.');
        return;
    }

    router.delete(`/queries/history/${id}`, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo eliminar la consulta del historial.');
        },
    });
}

const categoryLabels: Record<string, string> = {
    report: 'Reporte',
    audit: 'Auditoría',
    maintenance: 'Mantenimiento',
    exploration: 'Exploración',
    other: 'Otro',
};

type BadgeVariant = 'default' | 'outline' | 'destructive' | 'secondary';

const statusVariants: Record<string, BadgeVariant> = {
    success: 'default',
    failed: 'destructive',
    blocked: 'secondary',
};
</script>

<template>
    <Head title="Historial de consultas" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card>
            <CardHeader>
                <CardTitle>Historial de consultas</CardTitle>
                <CardDescription>Registro completo de consultas ejecutadas con clasificación y filtros.</CardDescription>
            </CardHeader>
            <CardContent class="text-sm">
                <Badge variant="secondary">{{ props.runs.total }} consultas</Badge>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Filtros</CardTitle>
                <CardDescription>Refina por estado, categoría, conexión, fechas y búsqueda.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-4">
                <div class="space-y-2">
                    <Label for="status">Estado</Label>
                    <Select v-model="form.status">
                        <SelectTrigger id="status">
                            <SelectValue placeholder="Todos" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Todos</SelectItem>
                            <SelectItem v-for="s in props.statuses" :key="s" :value="s">{{ s }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label for="category">Categoría</Label>
                    <Select v-model="form.category">
                        <SelectTrigger id="category">
                            <SelectValue placeholder="Todas" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Todas</SelectItem>
                            <SelectItem v-for="c in props.categories" :key="c" :value="c">
                                {{ categoryLabels[c] ?? c }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label for="search">Búsqueda</Label>
                    <Input id="search" v-model="form.search" placeholder="SQL, nota, categoría..." />
                </div>
                <div class="flex items-end gap-2">
                    <Button @click="applyFilters">Aplicar</Button>
                    <Button variant="secondary" @click="clearFilters">Limpiar</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Resultados</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="p-2">ID</th>
                                <th class="p-2">Estado</th>
                                <th class="p-2">Categoría</th>
                                <th class="p-2">Conexión</th>
                                <th class="p-2">Duración</th>
                                <th class="p-2">Filas</th>
                                <th class="p-2">SQL</th>
                                <th class="p-2">Fecha</th>
                                <th class="p-2">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in props.runs.data" :key="item.id" class="border-b">
                                <td class="p-2">#{{ item.id }}</td>
                                <td class="p-2">
                                    <Badge :variant="statusVariants[item.status] ?? 'outline'">{{ item.status }}</Badge>
                                </td>
                                <td class="p-2">{{ item.category ? (categoryLabels[item.category] ?? item.category) : '-' }}</td>
                                <td class="p-2">{{ item.connection?.name ?? 'N/A' }}</td>
                                <td class="p-2">{{ item.duration_ms }} ms</td>
                                <td class="p-2">{{ item.rows_returned }}</td>
                                <td class="max-w-[200px] truncate p-2 text-xs font-mono">{{ item.sql }}</td>
                                <td class="p-2">{{ item.created_at ?? 'N/A' }}</td>
                                <td class="flex gap-1 p-2">
                                    <Button size="sm" variant="outline" as-child>
                                        <a :href="`/queries/history/${item.id}`">Ver</a>
                                    </Button>
                                    <Button size="sm" variant="outline" @click="deleteRun(item.id)">Eliminar</Button>
                                </td>
                            </tr>
                            <tr v-if="props.runs.data.length === 0">
                                <td colspan="9" class="p-4 text-center text-muted-foreground">
                                    No se encontraron consultas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        v-for="(link, index) in props.runs.links"
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
