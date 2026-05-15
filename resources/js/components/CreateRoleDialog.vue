<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Check, Loader2, Plus, ShieldPlus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
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
import { toastActionError, toastActionSuccess } from '@/lib/actionToast';

type PermissionOption = {
    id: number;
    name: string;
    key: string;
};

type RoleItem = {
    id: number;
    name: string;
    key: string;
    permissions: Array<{ id: number; name: string; key: string }>;
};

const props = defineProps<{
    permissions: PermissionOption[];
    role?: RoleItem | null;
}>();

const emit = defineEmits<{
    saved: [];
}>();

const open = ref(false);
const isSubmitting = ref(false);
const serverError = ref<string | null>(null);
const isEditing = ref(false);
const selectedPermissionIds = ref<number[]>([]);

const form = reactive({
    name: '',
    key: '',
});

function onOpenChange(value: boolean) {
    open.value = value;
    if (value && props.role) {
        isEditing.value = true;
        form.name = props.role.name;
        form.key = props.role.key;
        selectedPermissionIds.value = props.role.permissions.map((p) => p.id);
    } else if (value) {
        isEditing.value = false;
        form.name = '';
        form.key = '';
        selectedPermissionIds.value = [];
    }
    serverError.value = null;
}

function togglePermission(permId: number) {
    const idx = selectedPermissionIds.value.indexOf(permId);
    if (idx === -1) {
        selectedPermissionIds.value.push(permId);
    } else {
        selectedPermissionIds.value.splice(idx, 1);
    }
}

function getXsrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    return token ? decodeURIComponent(token) : '';
}

async function handleSave() {
    isSubmitting.value = true;
    serverError.value = null;

    try {
        const url = isEditing.value
            ? `/admin/roles/${props.role!.id}`
            : '/admin/roles';
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
            throw new Error(body.message || 'Error al guardar rol.');
        }

        const roleId = isEditing.value ? props.role!.id : await findNewRoleId();
        if (roleId) {
            const permissionsResponse = await fetch(`/admin/roles/${roleId}/permissions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getXsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ permission_ids: selectedPermissionIds.value }),
            });

            if (!permissionsResponse.ok) {
                const body = await permissionsResponse.json().catch(() => ({}));
                throw new Error(body.message || 'No se pudieron guardar los permisos del rol.');
            }
        }

        open.value = false;
        toastActionSuccess(isEditing.value ? 'Rol actualizado.' : 'Rol creado.');
        router.reload({ only: ['roles', 'permissions'] });
    } catch (error) {
        serverError.value = error instanceof Error ? error.message : 'Error al guardar.';
        toastActionError(error, 'No se pudo guardar el rol.');
    } finally {
        isSubmitting.value = false;
    }
}

async function findNewRoleId(): Promise<number | null> {
    try {
        const response = await fetch('/admin/access-control', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        const roles = data.props?.roles ?? [];
        if (roles.length > 0) return roles[roles.length - 1].id;
    } catch { /* fall through */ }
    return null;
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogTrigger as-child>
            <slot>
                <Button variant="outline" size="sm" class="gap-1.5 text-xs">
                    <ShieldPlus class="size-3.5" />
                    Nuevo rol
                </Button>
            </slot>
        </DialogTrigger>

        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-sm">
                    {{ isEditing ? 'Editar rol' : 'Crear rol' }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{ isEditing ? 'Modifica el nombre, clave y permisos del rol.' : 'Define un nuevo rol y asigna sus permisos.' }}
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <Label for="rname" class="text-xs">Nombre</Label>
                        <Input id="rname" v-model="form.name" placeholder="Admin, Analista..." class="h-8 text-xs" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="rkey" class="text-xs">Clave</Label>
                        <Input id="rkey" v-model="form.key" placeholder="admin, analyst..." class="h-8 text-xs" />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <Label class="text-xs">Permisos</Label>
                    <div class="max-h-52 overflow-y-auto rounded-md border p-3">
                        <div class="flex flex-col gap-1">
                            <div
                                v-for="perm in permissions"
                                :key="perm.id"
                                class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs transition-colors hover:bg-accent"
                                @click="togglePermission(perm.id)"
                            >
                                <div
                                    class="flex size-4 shrink-0 items-center justify-center rounded border"
                                    :class="selectedPermissionIds.includes(perm.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-input'"
                                >
                                    <Check v-if="selectedPermissionIds.includes(perm.id)" class="size-3" />
                                </div>
                                <span class="flex-1">{{ perm.name }}</span>
                                <span class="text-[10px] text-muted-foreground">{{ perm.key }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="serverError" class="rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">
                    {{ serverError }}
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" size="sm" @click="open = false">Cancelar</Button>
                <Button size="sm" :disabled="!form.name || !form.key || isSubmitting" @click="handleSave">
                    <Loader2 v-if="isSubmitting" class="size-3.5 animate-spin" data-icon="inline-start" />
                    {{ isSubmitting ? 'Guardando...' : (isEditing ? 'Actualizar' : 'Crear rol') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
