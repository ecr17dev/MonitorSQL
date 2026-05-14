<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type BackupFile = {
    name: string;
    path: string;
    size_bytes: number | null;
    last_modified_at: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Backups',
                href: '/backups',
            },
        ],
    },
});

const props = defineProps<{
    files: BackupFile[];
    total_files: number;
}>();

function formatBytes(value: number | null): string {
    if (value === null) {
        return 'N/A';
    }

    const kb = 1024;
    const mb = kb * 1024;
    const gb = mb * 1024;

    if (value >= gb) {
        return `${(value / gb).toFixed(2)} GB`;
    }

    if (value >= mb) {
        return `${(value / mb).toFixed(2)} MB`;
    }

    if (value >= kb) {
        return `${(value / kb).toFixed(2)} KB`;
    }

    return `${value} B`;
}
</script>

<template>
    <Head title="Backups" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card>
            <CardHeader>
                <CardTitle>Estado de backups</CardTitle>
                <CardDescription>Archivos detectados en `storage/app/backups`.</CardDescription>
            </CardHeader>
            <CardContent class="flex items-center gap-2 text-sm">
                <Badge variant="secondary">{{ props.total_files }} archivos</Badge>
                <p class="text-muted-foreground">
                    Si no hay archivos, configura y ejecuta el job de respaldo del entorno.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Inventario de respaldos</CardTitle>
                <CardDescription>Listado rápido para revisión operativa.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="props.files.length === 0" class="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                    Todavía no se encontraron backups en el directorio esperado.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="p-2">Archivo</th>
                                <th class="p-2">Peso</th>
                                <th class="p-2">Actualizado</th>
                                <th class="p-2">Ruta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="file in props.files" :key="file.path" class="border-b">
                                <td class="p-2">{{ file.name }}</td>
                                <td class="p-2">{{ formatBytes(file.size_bytes) }}</td>
                                <td class="p-2">{{ file.last_modified_at ?? 'N/A' }}</td>
                                <td class="p-2 text-xs text-muted-foreground">{{ file.path }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
