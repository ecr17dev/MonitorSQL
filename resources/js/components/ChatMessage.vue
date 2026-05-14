<script setup lang="ts">
import { computed } from 'vue';
import { Bot, User } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps<{
    role: 'user' | 'assistant';
    content: string;
}>();

const isAssistant = computed(() => props.role === 'assistant');
</script>

<template>
    <div
        :class="
            cn(
                'flex gap-3 rounded-lg p-4',
                isAssistant ? 'bg-muted/50' : 'bg-background',
            )
        "
    >
        <div
            :class="
                cn(
                    'flex size-8 shrink-0 items-center justify-center rounded-full',
                    isAssistant ? 'bg-primary text-primary-foreground' : 'bg-secondary text-secondary-foreground',
                )
            "
        >
            <Bot v-if="isAssistant" class="size-4" />
            <User v-else class="size-4" />
        </div>
        <div class="flex-1 overflow-hidden">
            <p class="text-xs font-medium text-muted-foreground mb-1">
                {{ isAssistant ? 'MonitorSQL' : 'Tú' }}
            </p>
            <div class="text-sm whitespace-pre-wrap break-words">
                {{ content }}
            </div>
        </div>
    </div>
</template>
