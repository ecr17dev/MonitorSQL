<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import Heading from '@/components/Heading.vue';
import { toastActionError, toastActionSuccess } from '@/lib/actionToast';
import { edit as editSystemPrompt } from '@/routes/system-prompt';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Prompt del sistema', href: editSystemPrompt() },
        ],
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
        const response = await fetch('/settings/system-prompt', {
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

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || 'No se pudo guardar el prompt del sistema.');
        }

        toastActionSuccess('Prompt del sistema actualizado.');
        router.reload({ only: ['prompt'] });
    } catch (error) {
        toastActionError(error, 'No se pudo guardar el prompt del sistema.');
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Head title="Prompt del sistema" />
    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            title="Prompt del sistema"
            description="Instrucciones base para la IA que genera consultas SQL. Personaliza su comportamiento."
        />

        <div class="flex flex-col gap-3">
            <p class="text-xs text-muted-foreground">
                {{ prompt.description }}
            </p>
            <Textarea
                v-model="content"
                class="min-h-64 font-mono text-xs"
                placeholder="Ingresa instrucciones del prompt del sistema..."
            />
        </div>

        <div class="flex items-center gap-3">
            <Button :disabled="isSaving" @click="handleSave">
                {{ isSaving ? 'Guardando...' : 'Guardar prompt del sistema' }}
            </Button>
            <p class="text-xs text-muted-foreground">
                Los cambios se aplican en la próxima generación de consulta IA.
            </p>
        </div>
    </div>
</template>
