<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toastActionError, toastActionWarning } from '@/lib/actionToast';

type SavedConnection = {
    id: number;
    name: string;
};

type SavedItem = {
    id: number;
    name: string;
    sql: string;
    full_sql: string;
    is_favorite: boolean;
    category: string | null;
    tags: string[] | null;
    note: string | null;
    created_at: string | null;
    connection: SavedConnection | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedSaved = {
    data: SavedItem[];
    links: PaginationLink[];
    total: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Consultas guardadas', href: '/queries/saved' },
        ],
    },
});

const props = defineProps<{
    saved: PaginatedSaved;
    filters: {
        category: string;
        connection_id: string;
        is_favorite: string;
        search: string;
    };
    categories: string[];
}>();

const form = reactive({
    category: props.filters.category,
    connection_id: props.filters.connection_id,
    is_favorite: props.filters.is_favorite,
    search: props.filters.search,
});

const editingId = ref<number | null>(null);
const editName = ref('');
const editSql = ref('');

const categoryLabels: Record<string, string> = {
    report: 'Reporte',
    audit: 'Auditoría',
    maintenance: 'Mantenimiento',
    exploration: 'Exploración',
    other: 'Otro',
};

function applyFilters(): void {
    router.get('/queries/saved', form, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function clearFilters(): void {
    form.category = '';
    form.connection_id = '';
    form.is_favorite = '';
    form.search = '';
    applyFilters();
}

function beginEdit(item: SavedItem): void {
    editingId.value = item.id;
    editName.value = item.name;
    editSql.value = item.full_sql;
}

function cancelEdit(): void {
    editingId.value = null;
}

function saveEdit(id: number): void {
    router.put(`/queries/saved/${id}`, {
        name: editName.value,
        sql: editSql.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingId.value = null;
        },
        onError: (errors) => {
            toastActionError(errors, 'No se pudo guardar la consulta.');
        },
    });
}

function toggleFavorite(id: number, current: boolean): void {
    router.put(`/queries/saved/${id}`, {
        is_favorite: !current,
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo actualizar la consulta.');
        },
    });
}

function deleteSaved(id: number): void {
    if (!confirm('¿Eliminar esta consulta guardada?')) {
        toastActionWarning('Eliminación cancelada.');
        return;
    }

    router.delete(`/queries/saved/${id}`, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo eliminar la consulta guardada.');
        },
    });
}
</script>

<template>
    <Head title="Consultas guardadas" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card>
            <CardHeader>
                <CardTitle>Consultas guardadas</CardTitle>
                <CardDescription>Gestiona tus consultas guardadas, edítalas y organízalas por categoría.</CardDescription>
            </CardHeader>
            <CardContent class="text-sm">
                <Badge variant="secondary">{{ props.saved.total }} consultas guardadas</Badge>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Filtros</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-4">
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
                    <Input id="search" v-model="form.search" placeholder="Nombre, SQL, nota..." />
                </div>
                <div class="flex items-end gap-2 md:col-span-2">
                    <Button @click="applyFilters">Aplicar</Button>
                    <Button variant="secondary" @click="clearFilters">Limpiar</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Consultas</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="p-2">Nombre</th>
                                <th class="p-2">Categoría</th>
                                <th class="p-2">Conexión</th>
                                <th class="p-2">SQL</th>
                                <th class="p-2">Favorita</th>
                                <th class="p-2">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in props.saved.data" :key="item.id" class="border-b">
                                <td class="p-2 font-medium">
                                    <template v-if="editingId === item.id">
                                        <Input v-model="editName" class="w-full" />
                                    </template>
                                    <template v-else>{{ item.name }}</template>
                                </td>
                                <td class="p-2">{{ item.category ? (categoryLabels[item.category] ?? item.category) : '-' }}</td>
                                <td class="p-2">{{ item.connection?.name ?? 'N/A' }}</td>
                                <td class="max-w-[200px] truncate p-2 text-xs font-mono">
                                    <template v-if="editingId === item.id">
                                        <Input v-model="editSql" class="w-full" />
                                    </template>
                                    <template v-else>{{ item.sql }}</template>
                                </td>
                                <td class="p-2">
                                    <Button size="sm" variant="ghost" @click="toggleFavorite(item.id, item.is_favorite)">
                                        {{ item.is_favorite ? '★' : '☆' }}
                                    </Button>
                                </td>
                                <td class="flex gap-1 p-2">
                                    <template v-if="editingId === item.id">
                                        <Button size="sm" @click="saveEdit(item.id)">Guardar</Button>
                                        <Button size="sm" variant="secondary" @click="cancelEdit">Cancelar</Button>
                                    </template>
                                    <template v-else>
                                        <Button size="sm" variant="outline" @click="beginEdit(item)">Editar</Button>
                                        <Button size="sm" variant="outline" @click="deleteSaved(item.id)">Eliminar</Button>
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="props.saved.data.length === 0">
                                <td colspan="6" class="p-4 text-center text-muted-foreground">
                                    No hay consultas guardadas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        v-for="(link, index) in props.saved.links"
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
