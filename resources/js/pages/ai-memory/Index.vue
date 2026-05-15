<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { toastActionError, toastActionWarning } from '@/lib/actionToast';

type ProfileConnection = {
    id: number;
    name: string;
};

type MemoryProfile = {
    id: number;
    preferred_tables: Record<string, number> | null;
    term_aliases: Record<string, string> | null;
    successful_query_patterns: Record<string, number> | null;
    last_used_at: string | null;
    created_at: string | null;
    connection: ProfileConnection | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Memoria IA', href: '/ai-memory' },
        ],
    },
});

const props = defineProps<{
    profiles: MemoryProfile[];
}>();

function clearProfile(id: number): void {
    if (!confirm('¿Eliminar este perfil de memoria? La IA perderá el contexto aprendido para esta conexión.')) {
        toastActionWarning('Eliminación cancelada.');
        return;
    }

    router.delete(`/ai-memory/${id}`, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo eliminar el perfil de memoria.');
        },
    });
}

function clearAll(): void {
    if (!confirm('¿Eliminar TODOS los perfiles de memoria? La IA perderá todo el contexto aprendido.')) {
        toastActionWarning('Limpieza cancelada.');
        return;
    }

    router.post('/ai-memory/clear-all', {}, {
        preserveScroll: true,
        preserveState: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo limpiar la memoria de IA.');
        },
    });
}

function maxTableCount(tables: Record<string, number> | null): number {
    if (!tables) return 0;
    return Math.max(...Object.values(tables), 1);
}

const patternLabels: Record<string, string> = {
    basic_select: 'SELECT básico',
    aggregation: 'Agregación',
    counting: 'Conteo',
    window_function: 'Window functions',
    deduplication_window: 'Deduplicación',
    comparison_window: 'Comparación',
    cte_query: 'CTE / Subconsultas',
};
</script>

<template>
    <Head title="Memoria IA" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold">Memoria IA</h1>
                <p class="text-sm text-muted-foreground">
                    Perfiles de aprendizaje de la IA por conexión. La IA recuerda tus tablas preferidas, patrones de consulta y aliases de términos.
                </p>
            </div>
            <Button v-if="props.profiles.length > 0" variant="destructive" size="sm" @click="clearAll">
                Limpiar toda la memoria
            </Button>
        </div>

        <div v-if="props.profiles.length === 0" class="p-4 text-center text-muted-foreground">
            No hay perfiles de memoria. La IA comenzará a aprender cuando ejecutes consultas.
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Card v-for="profile in props.profiles" :key="profile.id">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>{{ profile.connection?.name ?? 'Sin conexión' }}</CardTitle>
                            <CardDescription>
                                Último uso: {{ profile.last_used_at ?? 'Nunca' }}
                            </CardDescription>
                        </div>
                        <Button size="sm" variant="outline" @click="clearProfile(profile.id)">Limpiar</Button>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-3">
                    <div v-if="profile.preferred_tables">
                        <h4 class="mb-1 text-xs font-semibold">Tablas preferidas</h4>
                        <div class="grid gap-1">
                            <div v-for="(count, table) in profile.preferred_tables" :key="String(table)" class="flex items-center gap-2">
                                <Badge variant="secondary" class="text-xs">{{ table }}</Badge>
                                <div class="bg-muted h-2 rounded-full" :style="{ width: Math.max((count / maxTableCount(profile.preferred_tables)) * 100, 8) + 'px' }" />
                                <span class="text-xs text-muted-foreground">{{ count }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="profile.term_aliases">
                        <h4 class="mb-1 text-xs font-semibold">Aliases de términos</h4>
                        <div class="flex flex-wrap gap-1">
                            <Badge v-for="(value, key) in profile.term_aliases" :key="String(key)" variant="outline" class="text-xs">
                                {{ key }} &rarr; {{ value }}
                            </Badge>
                        </div>
                    </div>

                    <div v-if="profile.successful_query_patterns">
                        <h4 class="mb-1 text-xs font-semibold">Patrones de consulta</h4>
                        <div class="flex flex-wrap gap-1">
                            <Badge v-for="(count, pattern) in profile.successful_query_patterns" :key="String(pattern)" variant="secondary" class="text-xs">
                                {{ patternLabels[pattern] ?? pattern }} ({{ count }})
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
