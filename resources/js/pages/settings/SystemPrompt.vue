<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import Heading from '@/components/Heading.vue';
import { edit as editSystemPrompt } from '@/routes/system-prompt';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'System Prompt', href: editSystemPrompt() }],
    },
});

const props = defineProps<{
    prompt: {
        key: string;
        content: string;
        description: string;
    };
}>();

const content = ref(props.prompt.content);
const isSaving = ref(false);

function getXsrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    return token ? decodeURIComponent(token) : '';
}

async function handleSave() {
    isSaving.value = true;
    try {
        await fetch('/settings/system-prompt', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ content: content.value }),
        });
        router.reload({ only: ['prompt'] });
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Head title="System Prompt" />
    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            title="System Prompt"
            description="Base instructions for the AI that generates SQL queries. Customize how the AI behaves."
        />

        <div class="flex flex-col gap-3">
            <p class="text-xs text-muted-foreground">
                {{ prompt.description }}
            </p>
            <Textarea
                v-model="content"
                class="min-h-64 font-mono text-xs"
                placeholder="Enter system prompt instructions..."
            />
        </div>

        <div class="flex items-center gap-3">
            <Button :disabled="isSaving" @click="handleSave">
                {{ isSaving ? 'Saving...' : 'Save System Prompt' }}
            </Button>
            <p class="text-xs text-muted-foreground">
                Changes take effect on the next AI query generation.
            </p>
        </div>
    </div>
</template>
