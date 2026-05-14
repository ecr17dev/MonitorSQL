<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Iniciar sesión',
        description: 'Ingresa tus credenciales para acceder a MonitorSQL',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <Label for="email" class="text-xs font-medium">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="usuario@ejemplo.com"
                    class="h-10"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="flex flex-col gap-1.5">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-xs font-medium">Contraseña</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs"
                        :tabindex="5"
                    >
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center gap-2">
                <Checkbox id="remember" name="remember" :tabindex="3" />
                <Label for="remember" class="text-xs font-normal cursor-pointer">Recordarme</Label>
            </div>

            <Button
                type="submit"
                class="mt-1 h-10 w-full bg-[#155974] text-white shadow-md shadow-[#155974]/10 transition-all hover:bg-[#0a2d51] hover:shadow-lg hover:shadow-[#155974]/20"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" data-icon="inline-start" />
                {{ processing ? 'Ingresando...' : 'Iniciar sesión' }}
            </Button>
        </div>

        <div
            v-if="canRegister"
            class="text-center text-xs text-muted-foreground"
        >
            ¿No tienes cuenta?
            <TextLink :href="register()" :tabindex="6" class="font-medium">
                Crear una cuenta
            </TextLink>
        </div>
    </Form>
</template>
