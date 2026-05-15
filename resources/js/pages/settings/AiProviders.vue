<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Check, Loader2, Wifi, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Heading from '@/components/Heading.vue';
import { toastActionError, toastActionSuccess } from '@/lib/actionToast';
import { edit as editAiProviders } from '@/routes/ai-providers';

type Provider = {
    provider: string;
    display_name: string;
    api_key: string;
    is_enabled: boolean;
    default_model: string;
};

type TestState = {
    testing: boolean;
    result: { success: boolean; message: string } | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Proveedores de IA', href: editAiProviders() },
        ],
    },
});

const props = defineProps<{
    providers: Provider[];
}>();

const formData = ref<Provider[]>(
    props.providers.map((p) => ({ ...p })),
);
const isSaving = ref(false);
const testStates = reactive<Record<string, TestState>>({});

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
        const response = await fetch('/settings/ai-providers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ providers: formData.value }),
        });

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || 'No se pudo guardar la configuración de proveedores IA.');
        }

        toastActionSuccess('Configuración de proveedores IA actualizada.');
        router.reload({ only: ['providers'] });
    } catch (error) {
        toastActionError(error, 'No se pudo guardar la configuración de proveedores IA.');
    } finally {
        isSaving.value = false;
    }
}

function toggleProvider(index: number) {
    formData.value[index].is_enabled = !formData.value[index].is_enabled;
}

async function testProvider(providerKey: string) {
    const state = testStates[providerKey] ?? { testing: false, result: null };
    testStates[providerKey] = state;
    state.testing = true;
    state.result = null;

    try {
        const response = await fetch('/settings/ai-providers/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ provider: providerKey }),
        });
        const data = await response.json();
        state.result = {
            success: data.success ?? false,
            message: data.message ?? 'Respuesta desconocida.',
        };
    } catch {
        state.result = {
            success: false,
            message: 'Error de red. No se pudo alcanzar el endpoint de prueba.',
        };
    } finally {
        state.testing = false;
    }
}
</script>

<template>
    <Head title="Proveedores de IA" />
    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            title="Proveedores de IA"
            description="Configura las API keys de los servicios de IA. Guarda primero tu clave y luego pruébala."
        />

        <div class="flex flex-col gap-4">
            <div
                v-for="(provider, index) in formData"
                :key="provider.provider"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none"
                            :class="provider.is_enabled ? 'bg-primary' : 'bg-muted'"
                            @click="toggleProvider(index)"
                        >
                            <span
                                class="pointer-events-none inline-block size-4 rounded-full bg-background shadow transition-transform"
                                :class="provider.is_enabled ? 'translate-x-4' : 'translate-x-0'"
                            />
                        </button>
                        <div>
                            <p class="text-sm font-medium">{{ provider.display_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ provider.provider }}</p>
                        </div>
                    </div>

                    <Button
                        v-if="provider.is_enabled"
                        variant="outline"
                        size="sm"
                        class="text-xs gap-1"
                        :disabled="testStates[provider.provider]?.testing"
                        @click="testProvider(provider.provider)"
                    >
                        <Loader2 v-if="testStates[provider.provider]?.testing" class="size-3 animate-spin" data-icon="inline-start" />
                        <Wifi v-else class="size-3" data-icon="inline-start" />
                        {{ testStates[provider.provider]?.testing ? 'Probando...' : 'Probar' }}
                    </Button>
                </div>

                <div
                    v-if="testStates[provider.provider]?.result"
                    class="mt-2 flex items-center gap-2 rounded-md px-3 py-1.5 text-xs"
                    :class="testStates[provider.provider]?.result?.success
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'bg-destructive/10 text-destructive'"
                >
                    <Check v-if="testStates[provider.provider]?.result?.success" class="size-3.5" />
                    <X v-else class="size-3.5" />
                    {{ testStates[provider.provider]?.result?.message }}
                </div>

                <div v-if="provider.is_enabled" class="mt-4 flex flex-col gap-3">
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs">Clave API</Label>
                        <Input
                            v-model="formData[index].api_key"
                            type="password"
                            :placeholder="provider.api_key ? '•••••••• (sin cambios)' : 'sk-...'"
                            class="h-8 text-xs"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs">Modelo por defecto (opcional)</Label>
                        <Input
                            v-model="formData[index].default_model"
                            placeholder="ej. gpt-4.1-mini, claude-3-5-sonnet..."
                            class="h-8 text-xs"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Button :disabled="isSaving" @click="handleSave">
                {{ isSaving ? 'Guardando...' : 'Guardar configuración de proveedores IA' }}
            </Button>
            <p class="text-xs text-muted-foreground">
                Las claves API se cifran en reposo. Guarda antes de probar.
            </p>
        </div>
    </div>
</template>
