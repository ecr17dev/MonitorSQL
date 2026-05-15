<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { toastActionError, toastActionWarning } from '@/lib/actionToast';

type RunConnection = {
    id: number;
    name: string;
    driver: string;
};

type RunDetail = {
    id: number;
    sql: string;
    normalized_sql: string | null;
    status: string;
    category: string | null;
    tags: string[] | null;
    note: string | null;
    is_favorite: boolean;
    duration_ms: number;
    rows_returned: number;
    is_ai_generated: boolean;
    error_message: string | null;
    meta: Record<string, unknown> | null;
    created_at: string | null;
    connection: RunConnection | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Historial de consultas', href: '/queries/history' },
            { title: 'Detalle', href: '' },
        ],
    },
});

const props = defineProps<{
    run: RunDetail;
    categories: string[];
    statuses: string[];
}>();

const editingNote = ref(false);
const noteDraft = ref(props.run.note ?? '');
const selectedCategory = ref(props.run.category ?? '');
const selectedFavorite = ref(props.run.is_favorite);

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

function updateRun(): void {
    router.put(`/queries/history/${props.run.id}`, {
        category: selectedCategory.value || null,
        note: noteDraft.value || null,
        is_favorite: selectedFavorite.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingNote.value = false;
        },
        onError: (errors) => {
            toastActionError(errors, 'No se pudo guardar la clasificación.');
        },
    });
}

function deleteRun(): void {
    if (!confirm('¿Eliminar esta consulta del historial?')) {
        toastActionWarning('Eliminación cancelada.');
        return;
    }

    router.delete(`/queries/history/${props.run.id}`, {
        onSuccess: () => {
            router.visit('/queries/history');
        },
        onError: (errors) => {
            toastActionError(errors, 'No se pudo eliminar la consulta del historial.');
        },
    });
}
</script>

<template>
    <Head title="Detalle de consulta" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold">Consulta #{{ props.run.id }}</h1>
                <p class="text-sm text-muted-foreground">{{ props.run.connection?.name ?? 'Sin conexión' }} · {{ props.run.created_at }}</p>
            </div>
            <div class="flex gap-2">
                <Button variant="destructive" size="sm" @click="deleteRun">Eliminar</Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">Estado</CardTitle>
                </CardHeader>
                <CardContent>
                    <Badge :variant="statusVariants[props.run.status] ?? 'outline'">{{ props.run.status }}</Badge>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">Duración</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-bold">{{ props.run.duration_ms }} ms</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">Filas retornadas</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-bold">{{ props.run.rows_returned }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">IA Generada</CardTitle>
                </CardHeader>
                <CardContent>
                    <Badge :variant="props.run.is_ai_generated ? 'default' : 'secondary'">
                        {{ props.run.is_ai_generated ? 'Sí' : 'No' }}
                    </Badge>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>SQL</CardTitle>
            </CardHeader>
            <CardContent>
                <pre class="overflow-x-auto rounded-md bg-muted p-4 text-xs font-mono whitespace-pre-wrap">{{ props.run.sql }}</pre>
            </CardContent>
        </Card>

        <Card v-if="props.run.error_message">
            <CardHeader>
                <CardTitle class="text-destructive">Error</CardTitle>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-destructive">{{ props.run.error_message }}</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Clasificación</CardTitle>
                <CardDescription>Categoriza y añade notas a esta consulta.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Categoría</Label>
                        <Select v-model="selectedCategory">
                            <SelectTrigger>
                                <SelectValue placeholder="Sin categoría" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Sin categoría</SelectItem>
                                <SelectItem v-for="c in props.categories" :key="c" :value="c">
                                    {{ categoryLabels[c] ?? c }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end gap-2">
                        <Button variant="outline" size="sm" @click="selectedFavorite = !selectedFavorite">
                            {{ selectedFavorite ? '★ Favorita' : '☆ Marcar favorita' }}
                        </Button>
                    </div>
                </div>
                <div class="space-y-2">
                    <Label>Nota</Label>
                    <template v-if="editingNote">
                        <Textarea v-model="noteDraft" rows="3" placeholder="Añade notas sobre esta consulta..." />
                        <div class="flex gap-2">
                            <Button size="sm" @click="updateRun">Guardar</Button>
                            <Button size="sm" variant="secondary" @click="editingNote = false; noteDraft = props.run.note ?? ''">Cancelar</Button>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-sm text-muted-foreground cursor-pointer hover:text-foreground" @click="editingNote = true">
                            {{ props.run.note || 'Sin notas. Haz clic para añadir.' }}
                        </p>
                    </template>
                </div>
                <div>
                    <Button @click="updateRun">Actualizar clasificación</Button>
                </div>
            </CardContent>
        </Card>

        <Card v-if="props.run.meta">
            <CardHeader>
                <CardTitle>Metadatos</CardTitle>
            </CardHeader>
            <CardContent>
                <pre class="overflow-x-auto rounded-md bg-muted p-4 text-xs">{{ JSON.stringify(props.run.meta, null, 2) }}</pre>
            </CardContent>
        </Card>
    </div>
</template>
