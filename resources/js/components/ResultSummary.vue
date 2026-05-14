<script setup lang="ts">
import { ref, watch } from 'vue';
import { Bot } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { QueryResult } from '@/composables/useQueryExecution';

const props = defineProps<{
    result: QueryResult;
    sql: string;
    connectionId: number;
}>();

const summary = ref<string | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);

watch(
    () => [props.result, props.sql],
    () => {
        summary.value = null;
        error.value = null;
    },
);

function getXsrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    return token ? decodeURIComponent(token) : '';
}

async function generateSummary() {
    isLoading.value = true;
    error.value = null;

    try {
        const sampleRows = props.result.rows.slice(0, 20);
        const response = await fetch('/queries/ai-summary', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                connection_id: props.connectionId,
                sql: props.sql,
                result_sample: {
                    columns: props.result.columns.map((c) => ({ name: c.name, type: c.type })),
                    rows: sampleRows,
                    row_count: props.result.meta.row_count,
                },
            }),
        });

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message ?? 'Error al generar el resumen.');
        }

        const data = await response.json();
        summary.value = data.summary ?? 'No se pudo generar el resumen.';
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Error al generar el resumen.';
    } finally {
        isLoading.value = false;
    }
}
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-sm font-medium">
                <Bot class="size-4" />
                Resumen IA
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="!summary && !isLoading" class="flex flex-col items-center gap-3 py-4">
                <p class="text-sm text-muted-foreground">
                    Genera un resumen automático de estos resultados con IA.
                </p>
                <Button variant="secondary" size="sm" @click="generateSummary">
                    <Bot class="size-3" data-icon="inline-start" />
                    Generar Resumen
                </Button>
            </div>

            <div v-else-if="isLoading" class="flex items-center justify-center gap-2 py-8">
                <span class="text-sm text-muted-foreground">Analizando resultados...</span>
            </div>

            <div v-else-if="error" class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                {{ error }}
            </div>

            <p v-else class="text-sm whitespace-pre-wrap leading-relaxed">
                {{ summary }}
            </p>
        </CardContent>
    </Card>
</template>
