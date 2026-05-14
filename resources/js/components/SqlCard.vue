<script setup lang="ts">
import { computed, ref } from 'vue';
import { Check, Copy, Edit3, Play, Save, Sparkles } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { AiGeneratedSql } from '@/composables/useSqlGeneration';

const props = defineProps<{
    generated: AiGeneratedSql;
    isExecuting?: boolean;
    showSql?: boolean;
}>();

const emit = defineEmits<{
    execute: [];
    edit: [sql: string];
    copy: [];
    save: [];
    explain: [];
}>();

const isEditing = ref(false);
const editedSql = ref('');
const copied = ref(false);

const confidenceBadge = computed(() => {
    switch (props.generated.confidence) {
        case 'high':
            return { variant: 'default' as const, label: 'Alta confianza' };
        case 'medium':
            return { variant: 'secondary' as const, label: 'Confianza media' };
        default:
            return { variant: 'destructive' as const, label: 'Requiere revisión' };
    }
});

const statusBadge = computed(() => {
    if (props.generated.confidence === 'low') {
        return { variant: 'destructive' as const, label: 'Requiere revisión' };
    }
    return { variant: 'secondary' as const, label: 'Seguro' };
});

function startEditing() {
    editedSql.value = props.generated.sql;
    isEditing.value = true;
}

function confirmEdit() {
    emit('edit', editedSql.value);
    isEditing.value = false;
}

function cancelEdit() {
    isEditing.value = false;
    editedSql.value = '';
}

async function handleCopy() {
    const sql = isEditing.value ? editedSql.value : props.generated.sql;
    try {
        await navigator.clipboard.writeText(sql);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // clipboard not available
    }
    emit('copy');
}
</script>

<template>
    <Card class="border-primary/30 bg-primary/5">
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2 text-sm font-medium">
                    <Sparkles class="size-4 text-primary" />
                    SQL Generado por IA
                </CardTitle>
                <div class="flex items-center gap-2">
                    <Badge :variant="confidenceBadge.variant" class="text-[10px]">
                        {{ confidenceBadge.label }}
                    </Badge>
                    <Badge :variant="statusBadge.variant" class="text-[10px]">
                        {{ statusBadge.label }}
                    </Badge>
                </div>
            </div>
        </CardHeader>
        <CardContent class="pb-2">
            <p class="text-xs text-muted-foreground mb-2">{{ generated.explanation }}</p>

            <div v-if="isEditing">
                <Textarea
                    v-model="editedSql"
                    class="min-h-24 font-mono text-xs"
                    @keyup.escape="cancelEdit"
                />
            </div>
            <pre
                v-else
                :class="
                    cn(
                        'overflow-x-auto rounded-md bg-background p-3 text-xs font-mono',
                        !showSql && 'cursor-pointer',
                    )
                "
            ><code>{{ showSql ? generated.sql : 'SQL oculto (click para ver)' }}</code></pre>

            <div v-if="generated.tables_used.length" class="mt-2 flex flex-wrap items-center gap-1">
                <span class="text-[10px] text-muted-foreground">Tablas:</span>
                <Badge
                    v-for="table in generated.tables_used"
                    :key="table"
                    variant="outline"
                    class="text-[10px]"
                >
                    {{ table }}
                </Badge>
            </div>

            <div v-if="generated.suggested_visualization?.type && generated.suggested_visualization.type !== 'table'" class="mt-2">
                <p class="text-[10px] text-muted-foreground">
                    Gráfico sugerido:
                    <span class="font-medium">{{ generated.suggested_visualization.type }}</span>
                    <span v-if="generated.suggested_visualization.reason">
                        — {{ generated.suggested_visualization.reason }}
                    </span>
                </p>
            </div>
        </CardContent>
        <CardFooter class="flex flex-wrap gap-2 pt-0">
            <Button size="sm" :disabled="isExecuting" @click="emit('execute')">
                <Play class="size-3" data-icon="inline-start" />
                Ejecutar
            </Button>

            <template v-if="isEditing">
                <Button size="sm" variant="secondary" @click="confirmEdit">
                    <Check class="size-3" data-icon="inline-start" />
                    Confirmar
                </Button>
                <Button size="sm" variant="ghost" @click="cancelEdit">
                    Cancelar
                </Button>
            </template>
            <template v-else>
                <Button size="sm" variant="secondary" @click="startEditing">
                    <Edit3 class="size-3" data-icon="inline-start" />
                    Editar
                </Button>
                <Button size="sm" variant="secondary" @click="handleCopy">
                    <Copy v-if="!copied" class="size-3" data-icon="inline-start" />
                    <Check v-else class="size-3" data-icon="inline-start" />
                    {{ copied ? 'Copiado' : 'Copiar' }}
                </Button>
                <Button size="sm" variant="secondary" @click="emit('save')">
                    <Save class="size-3" data-icon="inline-start" />
                    Guardar
                </Button>
            </template>
        </CardFooter>
    </Card>
</template>
