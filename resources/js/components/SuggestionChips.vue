<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{
    suggestions: string[];
}>();

const emit = defineEmits<{
    select: [prompt: string];
}>();
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Badge
            v-for="suggestion in suggestions"
            :key="suggestion"
            variant="outline"
            class="cursor-pointer transition-colors hover:bg-accent hover:text-accent-foreground"
            @click="emit('select', suggestion)"
        >
            {{ suggestion }}
        </Badge>
    </div>
</template>
