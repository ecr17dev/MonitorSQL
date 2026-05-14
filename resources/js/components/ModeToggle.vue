<script setup lang="ts">
import { Code2, Sparkles } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

defineProps<{
    mode: 'simple' | 'analyst';
}>();

const emit = defineEmits<{
    toggle: [];
}>();
</script>

<template>
    <div class="flex items-center gap-1 rounded-lg bg-muted p-1">
        <Button
            variant="ghost"
            size="sm"
            :class="cn('gap-1.5 text-xs', mode === 'simple' && 'bg-background shadow-sm')"
            @click="mode === 'analyst' ? emit('toggle') : undefined"
        >
            <Sparkles class="size-3.5" />
            Simple
        </Button>
        <Button
            variant="ghost"
            size="sm"
            :class="cn('gap-1.5 text-xs', mode === 'analyst' && 'bg-background shadow-sm')"
            @click="mode === 'simple' ? emit('toggle') : undefined"
        >
            <Code2 class="size-3.5" />
            Analista
        </Button>
    </div>
</template>
