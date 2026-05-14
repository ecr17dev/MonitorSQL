<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Crear cuenta',
        description: 'Registrate para empezar a consultar tus bases de datos',
    },
});
</script>

<template>
    <Head title="Registrarse" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <Label for="name" class="text-xs font-medium">Nombre completo</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Tu nombre"
                    class="h-10"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="email" class="text-xs font-medium">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="usuario@ejemplo.com"
                    class="h-10"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="password" class="text-xs font-medium">Contraseña</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Mínimo 8 caracteres"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="password_confirmation" class="text-xs font-medium">Confirmar contraseña</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Repite tu contraseña"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-1 h-10 w-full bg-[#155974] text-white shadow-md shadow-[#155974]/10 transition-all hover:bg-[#0a2d51] hover:shadow-lg hover:shadow-[#155974]/20"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" data-icon="inline-start" />
                {{ processing ? 'Creando cuenta...' : 'Crear cuenta' }}
            </Button>
        </div>

        <div class="text-center text-xs text-muted-foreground">
            ¿Ya tienes cuenta?
            <TextLink :href="login()" :tabindex="6" class="font-medium">
                Iniciar sesión
            </TextLink>
        </div>
    </Form>
</template>
