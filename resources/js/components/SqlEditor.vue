<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    modelValue: string;
    disabled?: boolean;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
    execute: [];
}>();

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        emit('execute');
    }
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <Label class="text-xs text-muted-foreground">SQL Workspace</Label>
        <Textarea
            :model-value="modelValue"
            :disabled="disabled"
            :placeholder="placeholder ?? 'Escribe o pega tu consulta SQL aquí...'"
            class="min-h-32 font-mono text-sm"
            @update:model-value="emit('update:modelValue', $event as string)"
            @keydown="onKeydown"
        />
        <p class="text-[10px] text-muted-foreground">
            Ctrl+Enter para ejecutar. Solo se permiten consultas de lectura (SELECT).
        </p>
    </div>
</template>
