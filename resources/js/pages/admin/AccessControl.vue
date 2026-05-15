<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { Edit3, ShieldPlus, Trash2, UserPlus } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import CreateUserDialog from '@/components/CreateUserDialog.vue';
import CreateRoleDialog from '@/components/CreateRoleDialog.vue';
import { toastActionError, toastActionWarning } from '@/lib/actionToast';

type RolePermission = { id: number; name: string; key: string };

type Role = {
    id: number;
    name: string;
    key: string;
    users_count: number;
    permissions: RolePermission[];
};

type UserRole = { id: number; name: string; key: string };

type UserItem = {
    id: number;
    name: string;
    email: string;
    created_at: string | null;
    roles: UserRole[];
};

type Permission = { id: number; name: string; key: string };

type PaginatedUsers = { data: UserItem[]; total: number };

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Usuarios, permisos y roles', href: '/admin/access-control' },
        ],
    },
});

const props = defineProps<{
    users: PaginatedUsers;
    roles: Role[];
    permissions: Permission[];
}>();

function deleteUser(userId: number) {
    if (!confirm('¿Eliminar este usuario?')) {
        toastActionWarning('Eliminación cancelada.');
        return;
    }

    router.delete(`/admin/users/${userId}`, {
        preserveScroll: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo eliminar el usuario.');
        },
    });
}

function deleteRole(roleId: number) {
    if (!confirm('¿Eliminar este rol?')) {
        toastActionWarning('Eliminación cancelada.');
        return;
    }

    router.delete(`/admin/roles/${roleId}`, {
        preserveScroll: true,
        onError: (errors) => {
            toastActionError(errors, 'No se pudo eliminar el rol.');
        },
    });
}
</script>

<template>
    <Head title="Usuarios, permisos y roles" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm flex items-center justify-between">
                        Usuarios
                        <CreateUserDialog :roles="roles" />
                    </CardTitle>
                    <CardDescription class="text-xs">Gestión de accesos.</CardDescription>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{ props.users.total }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm flex items-center justify-between">
                        Roles
                        <CreateRoleDialog :permissions="permissions" />
                    </CardTitle>
                    <CardDescription class="text-xs">Perfiles con permisos.</CardDescription>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{ props.roles.length }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">Permisos</CardTitle>
                    <CardDescription class="text-xs">Catálogo de autorización.</CardDescription>
                </CardHeader>
                <CardContent class="text-2xl font-semibold">{{ props.permissions.length }}</CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">Roles y sus permisos</CardTitle>
                    <CardDescription class="text-xs">Clic en un rol para editar sus permisos.</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <div v-for="role in props.roles" :key="role.id" class="rounded-md border p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium">{{ role.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ role.key }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="secondary" class="text-[10px]">{{ role.users_count }} usuarios</Badge>
                                <CreateRoleDialog :permissions="permissions" :role="role">
                                    <Button variant="ghost" size="icon" class="size-7">
                                        <Edit3 class="size-3.5" />
                                    </Button>
                                </CreateRoleDialog>
                                <Button variant="ghost" size="icon" class="size-7 text-destructive hover:text-destructive" @click="deleteRole(role.id)">
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <Badge v-for="perm in role.permissions" :key="perm.id" variant="outline" class="text-[10px]">
                                {{ perm.key }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm">Catálogo de permisos</CardTitle>
                    <CardDescription class="text-xs">Lista global de referencia.</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-1.5">
                    <Badge v-for="perm in props.permissions" :key="perm.id" variant="outline" class="text-[10px]">
                        {{ perm.key }}
                    </Badge>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm">Usuarios y roles asignados</CardTitle>
                <CardDescription class="text-xs">Administra usuarios y sus roles.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="p-2">Nombre</th>
                                <th class="p-2">Email</th>
                                <th class="p-2">Roles</th>
                                <th class="p-2">Creado</th>
                                <th class="p-2 w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in props.users.data" :key="user.id" class="border-b">
                                <td class="p-2">{{ user.name }}</td>
                                <td class="p-2">{{ user.email }}</td>
                                <td class="p-2">
                                    <div class="flex flex-wrap gap-1">
                                        <Badge v-for="role in user.roles" :key="role.id" variant="secondary" class="text-[10px]">
                                            {{ role.key }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="p-2 text-xs text-muted-foreground">{{ user.created_at ?? '-' }}</td>
                                <td class="p-2">
                                    <div class="flex items-center gap-1">
                                        <CreateUserDialog :roles="roles" :user="user">
                                            <Button variant="ghost" size="icon" class="size-7">
                                                <Edit3 class="size-3.5" />
                                            </Button>
                                        </CreateUserDialog>
                                        <Button variant="ghost" size="icon" class="size-7 text-destructive hover:text-destructive" @click="deleteUser(user.id)">
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
