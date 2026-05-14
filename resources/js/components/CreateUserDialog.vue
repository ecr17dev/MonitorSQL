<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Check, Loader2, Plus, UserPlus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type RoleOption = {
    id: number;
    name: string;
    key: string;
};

type UserItem = {
    id: number;
    name: string;
    email: string;
    roles: Array<{ id: number; name: string; key: string }>;
};

const props = defineProps<{
    roles: RoleOption[];
    user?: UserItem | null;
}>();

const emit = defineEmits<{
    saved: [];
}>();

const open = ref(false);
const isSubmitting = ref(false);
const serverError = ref<string | null>(null);
const isEditing = ref(false);
const selectedRoleIds = ref<number[]>([]);

const form = reactive({
    name: '',
    email: '',
    password: '',
});

function onOpenChange(value: boolean) {
    open.value = value;
    if (value && props.user) {
        isEditing.value = true;
        form.name = props.user.name;
        form.email = props.user.email;
        form.password = '';
        selectedRoleIds.value = props.user.roles.map((r) => r.id);
    } else if (value) {
        isEditing.value = false;
        form.name = '';
        form.email = '';
        form.password = '';
        selectedRoleIds.value = [];
    }
    serverError.value = null;
}

function toggleRole(roleId: number) {
    const idx = selectedRoleIds.value.indexOf(roleId);
    if (idx === -1) {
        selectedRoleIds.value.push(roleId);
    } else {
        selectedRoleIds.value.splice(idx, 1);
    }
}

async function handleSave() {
    isSubmitting.value = true;
    serverError.value = null;

    try {
        const url = isEditing.value
            ? `/admin/users/${props.user!.id}`
            : '/admin/users';
        const method = isEditing.value ? 'put' : 'post';

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(form),
        });

        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(body.message || 'Error al guardar usuario.');
        }

        if (selectedRoleIds.value.length > 0 || isEditing.value) {
            const userId = isEditing.value ? props.user!.id : await findNewUserId();
            if (userId) {
                await fetch(`/admin/users/${userId}/roles`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': getXsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ role_ids: selectedRoleIds.value }),
                });
            }
        }

        open.value = false;
        router.reload({ only: ['users'] });
    } catch (error) {
        serverError.value = error instanceof Error ? error.message : 'Error al guardar.';
    } finally {
        isSubmitting.value = false;
    }
}

async function findNewUserId(): Promise<number | null> {
    try {
        const response = await fetch('/admin/access-control', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        const users = data.props?.users?.data ?? [];
        if (users.length > 0) return users[0].id;
    } catch { /* fall through */ }
    return null;
}

function getXsrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    return token ? decodeURIComponent(token) : '';
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogTrigger as-child>
            <slot>
                <Button variant="outline" size="sm" class="gap-1.5 text-xs">
                    <UserPlus class="size-3.5" />
                    Nuevo usuario
                </Button>
            </slot>
        </DialogTrigger>

        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-sm">
                    {{ isEditing ? 'Editar usuario' : 'Crear usuario' }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{ isEditing ? 'Modifica los datos y roles del usuario.' : 'Crea un nuevo usuario y asigna sus roles.' }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <Label for="uname" class="text-xs">Nombre</Label>
                    <Input id="uname" v-model="form.name" placeholder="Nombre completo" class="h-8 text-xs" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="uemail" class="text-xs">Email</Label>
                    <Input id="uemail" v-model="form.email" type="email" placeholder="usuario@ejemplo.com" class="h-8 text-xs" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label for="upass" class="text-xs">
                        {{ isEditing ? 'Contraseña (dejar vacío para mantener)' : 'Contraseña' }}
                    </Label>
                    <Input id="upass" v-model="form.password" type="password" placeholder="••••••••" class="h-8 text-xs" />
                </div>

                <div class="flex flex-col gap-2">
                    <Label class="text-xs">Roles</Label>
                    <div class="flex flex-wrap gap-2 rounded-md border p-3">
                        <div
                            v-for="role in roles"
                            :key="role.id"
                            class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-xs transition-colors hover:bg-accent"
                            @click="toggleRole(role.id)"
                        >
                            <div
                                class="flex size-4 shrink-0 items-center justify-center rounded border"
                                :class="selectedRoleIds.includes(role.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-input'"
                            >
                                <Check v-if="selectedRoleIds.includes(role.id)" class="size-3" />
                            </div>
                            <span>{{ role.name }}</span>
                            <span class="text-[10px] text-muted-foreground">({{ role.key }})</span>
                        </div>
                    </div>
                </div>

                <div v-if="serverError" class="rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">
                    {{ serverError }}
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" size="sm" @click="open = false">Cancelar</Button>
                <Button size="sm" :disabled="!form.name || !form.email || (!isEditing && !form.password) || isSubmitting" @click="handleSave">
                    <Loader2 v-if="isSubmitting" class="size-3.5 animate-spin" data-icon="inline-start" />
                    {{ isSubmitting ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear usuario') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
