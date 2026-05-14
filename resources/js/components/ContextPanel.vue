<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    Database,
    Loader2,
    Plus,
    Search,
    Star,
    Table2,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import SuggestionChips from '@/components/SuggestionChips.vue';
import CreateConnectionDialog from '@/components/CreateConnectionDialog.vue';
import type { Connection, TableSummary } from '@/composables/useContext';

const props = defineProps<{
    connections: Connection[];
    selectedConnectionId: number | null;
    selectedTables: string[];
}>();

const emit = defineEmits<{
    selectConnection: [id: number];
    toggleTable: [table: string];
    selectSuggestion: [prompt: string];
    newChat: [];
}>();

const tables = ref<TableSummary[]>([]);
const tableSearch = ref('');
const isLoadingTables = ref(false);
const tableError = ref<string | null>(null);
const previousConnectionCount = ref(props.connections.length);

function getXsrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    return token ? decodeURIComponent(token) : '';
}

async function fetchTables(connectionId: number) {
    isLoadingTables.value = true;
    tableError.value = null;
    try {
        const response = await fetch(`/connections/${connectionId}/tables`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Failed to load tables');
        const data = await response.json();
        tables.value = data.tables ?? data.data ?? [];
    } catch (e) {
        tableError.value = e instanceof Error ? e.message : 'Error loading tables';
        tables.value = [];
    } finally {
        isLoadingTables.value = false;
    }
}

watch(
    () => props.selectedConnectionId,
    (newId) => {
        if (newId !== null) {
            fetchTables(newId);
        } else {
            tables.value = [];
        }
    },
    { immediate: true },
);

const filteredTables = ref<TableSummary[]>([]);

watch([tables, tableSearch], () => {
    const search = tableSearch.value.toLowerCase().trim();
    if (!search) {
        filteredTables.value = tables.value;
        return;
    }
    filteredTables.value = tables.value.filter(
        (t) => t.name.toLowerCase().includes(search) || (t.schema && t.schema.toLowerCase().includes(search)),
    );
}, { immediate: true });

watch(
    () => props.connections,
    (newConns) => {
        if (newConns.length > previousConnectionCount.value && newConns.length > 0) {
            const newConn = newConns[0];
            emit('selectConnection', newConn.id);
        }
        previousConnectionCount.value = newConns.length;
    },
    { deep: true },
);

const defaultSuggestions = [
    'Ver últimos 100 registros',
    'Buscar duplicados',
    'Contar por estado',
    'Agrupar por fecha',
    'Encontrar valores nulos',
    'Top 10 por monto',
    'Comparar este mes vs mes anterior',
    'Distribución por categoría',
];

function onConnectionCreated() {
    // The connections list will auto-update via the watcher above
}
</script>

<template>
    <div class="flex h-full flex-col gap-3 p-3">
        <Card>
            <CardHeader class="pb-2">
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-xs font-medium uppercase text-muted-foreground">
                        <Database class="size-3.5" />
                        Conexión
                    </CardTitle>
                    <CreateConnectionDialog @created="onConnectionCreated" />
                </div>
            </CardHeader>
            <CardContent class="flex flex-col gap-2">
                <select
                    :value="selectedConnectionId ?? ''"
                    class="border-input focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-xs outline-none focus-visible:ring-[3px]"
                    @change="emit('selectConnection', Number(($event.target as HTMLSelectElement).value))"
                >
                    <option value="" disabled>Seleccionar conexión</option>
                    <option
                        v-for="conn in connections"
                        :key="conn.id"
                        :value="conn.id"
                    >
                        {{ conn.name }} ({{ conn.driver }})
                    </option>
                </select>

                <div v-if="connections.length === 0" class="rounded-md border border-dashed p-4 text-center">
                    <p class="text-xs text-muted-foreground mb-3">
                        No hay conexiones configuradas.
                    </p>
                    <CreateConnectionDialog>
                        <Button variant="secondary" size="sm" class="gap-1.5 text-xs">
                            <Plus class="size-3.5" />
                            Crear primera conexión
                        </Button>
                    </CreateConnectionDialog>
                </div>
            </CardContent>
        </Card>

        <Card v-if="selectedConnectionId !== null" class="flex-1">
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-xs font-medium uppercase text-muted-foreground">
                    <Table2 class="size-3.5" />
                    Tablas
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-2">
                <div class="relative">
                    <Search class="absolute left-2 top-1/2 size-3 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="tableSearch"
                        placeholder="Filtrar tablas..."
                        class="h-7 pl-7 text-xs"
                    />
                </div>

                <div v-if="isLoadingTables" class="flex items-center justify-center gap-2 py-4">
                    <Loader2 class="size-4 animate-spin text-muted-foreground" />
                    <span class="text-xs text-muted-foreground">Cargando...</span>
                </div>

                <div v-else-if="tableError" class="text-xs text-destructive">
                    {{ tableError }}
                </div>

                <div v-else class="max-h-64 overflow-y-auto">
                    <div class="flex flex-col gap-1">
                        <div
                            v-for="table in filteredTables"
                            :key="table.name"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-accent"
                            :class="{
                                'bg-accent text-accent-foreground': selectedTables.includes(table.name),
                            }"
                            @click="emit('toggleTable', table.name)"
                        >
                            <Table2 class="size-3 text-muted-foreground" />
                            <span class="flex-1 truncate">{{ table.name }}</span>
                            <Badge
                                v-if="table.schema"
                                variant="outline"
                                class="text-[9px] px-1 py-0"
                            >
                                {{ table.schema }}
                            </Badge>
                        </div>
                        <p v-if="filteredTables.length === 0 && tables.length > 0" class="px-2 py-1 text-xs text-muted-foreground">
                            Sin coincidencias.
                        </p>
                        <p v-else-if="filteredTables.length === 0" class="px-2 py-1 text-xs text-muted-foreground">
                            No se encontraron tablas.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-xs font-medium uppercase text-muted-foreground">
                    <Star class="size-3.5" />
                    Sugerencias
                </CardTitle>
            </CardHeader>
            <CardContent>
                <SuggestionChips
                    :suggestions="defaultSuggestions"
                    @select="emit('selectSuggestion', $event)"
                />
            </CardContent>
        </Card>
    </div>
</template>
