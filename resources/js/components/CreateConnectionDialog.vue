<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Check,
    Database,
    Globe,
    Key,
    Loader2,
    Plug,
    Plus,
    Shield,
    User,
    Wifi,
} from 'lucide-vue-next';
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
import { Spinner } from '@/components/ui/spinner';
import { Badge } from '@/components/ui/badge';
import { toastActionError, toastActionSuccess } from '@/lib/actionToast';

type Driver = 'pgsql' | 'mysql' | 'mariadb';

const driverDefaults: Record<Driver, { label: string; port: number }> = {
    pgsql: { label: 'PostgreSQL', port: 5432 },
    mysql: { label: 'MySQL', port: 3306 },
    mariadb: { label: 'MariaDB', port: 3306 },
};

const emit = defineEmits<{
    created: [connectionId: number];
}>();

const open = ref(false);
const isSubmitting = ref(false);
const isTesting = ref(false);
const testResult = ref<{ success: boolean; message: string } | null>(null);
const serverError = ref<string | null>(null);

const form = reactive({
    name: '',
    driver: 'pgsql' as Driver,
    host: '',
    port: 5432,
    database: '',
    username: '',
    password: '',
    ssl_enabled: false,
    max_rows: 1000,
    query_timeout_seconds: 30,
});

function getXsrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    return token ? decodeURIComponent(token) : '';
}

function resetForm() {
    form.name = '';
    form.driver = 'pgsql';
    form.host = '';
    form.port = 5432;
    form.database = '';
    form.username = '';
    form.password = '';
    form.ssl_enabled = false;
    form.max_rows = 1000;
    form.query_timeout_seconds = 30;
    testResult.value = null;
    serverError.value = null;
}

function onOpenChange(value: boolean) {
    open.value = value;
    if (!value) {
        resetForm();
    }
}

function onDriverChange(event: Event) {
    const driver = (event.target as HTMLSelectElement).value as Driver;
    form.driver = driver;
    form.port = driverDefaults[driver].port;
}

function canSave(): boolean {
    return (
        form.name.trim() !== '' &&
        form.host.trim() !== '' &&
        form.database.trim() !== '' &&
        form.username.trim() !== '' &&
        form.password.trim() !== ''
    );
}

async function handleSave() {
    if (!canSave()) return;

    isSubmitting.value = true;
    serverError.value = null;

    try {
        const response = await fetch('/connections', {
            method: 'POST',
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
            const message =
                body.message ||
                (body.errors
                    ? Object.values(body.errors).flat().join('. ')
                    : 'Error al crear la conexión.');
            throw new Error(message);
        }

        const createdConnectionId = await identifyNewConnection();

        open.value = false;
        emit('created', createdConnectionId);
        toastActionSuccess('Conexión creada.');

        router.reload({ only: ['connections'] });
    } catch (error) {
        serverError.value = error instanceof Error ? error.message : 'Error al crear la conexión.';
        toastActionError(error, 'No se pudo guardar la conexión.');
    } finally {
        isSubmitting.value = false;
    }
}

async function identifyNewConnection(): Promise<number> {
    try {
        const response = await fetch('/connections', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));
        const connections = data.props?.connections?.data ?? data.connections ?? [];

        if (Array.isArray(connections) && connections.length > 0) {
            return connections[0].id;
        }
    } catch {
        // fall through
    }

    return 0;
}

async function handleTest(connectionId: number) {
    if (!connectionId) return;

    isTesting.value = true;
    testResult.value = null;

    try {
        const response = await fetch(`/connections/${connectionId}/test`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        });

        const data = await response.json();
        testResult.value = {
            success: data.success ?? response.ok,
            message: data.message ?? (response.ok ? 'Conexión exitosa.' : 'Falló la verificación.'),
        };
    } catch {
        testResult.value = {
            success: false,
            message: 'No se pudo verificar la conexión.',
        };
    } finally {
        isTesting.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogTrigger as-child>
            <slot>
                <Button variant="outline" size="sm" class="w-full gap-1.5 text-xs">
                    <Plus class="size-3.5" />
                    Nueva conexión
                </Button>
            </slot>
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-sm">
                    <Database class="size-4" />
                    Nueva conexión a base de datos
                </DialogTitle>
                <DialogDescription class="text-xs">
                    Configura una conexión remota a MySQL, MariaDB o PostgreSQL. Las credenciales se almacenan encriptadas.
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-4 max-h-96 overflow-y-auto px-0.5">
                <div class="flex flex-col gap-1.5">
                    <Label for="conn-name" class="text-xs">Nombre</Label>
                    <Input id="conn-name" v-model="form.name" placeholder="Producción, Staging, etc." class="h-8 text-xs" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <Label for="conn-driver" class="text-xs">Motor</Label>
                        <select
                            id="conn-driver"
                            :value="form.driver"
                            class="border-input focus-visible:ring-ring/50 h-8 w-full rounded-md border bg-transparent px-3 py-1 text-xs outline-none focus-visible:ring-[3px]"
                            @change="onDriverChange"
                        >
                            <option value="pgsql">PostgreSQL</option>
                            <option value="mysql">MySQL</option>
                            <option value="mariadb">MariaDB</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="conn-port" class="text-xs">Puerto</Label>
                        <Input id="conn-port" v-model.number="form.port" type="number" class="h-8 text-xs" />
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="conn-host" class="flex items-center gap-1 text-xs">
                        <Globe class="size-3" />
                        Host / IP
                    </Label>
                    <Input id="conn-host" v-model="form.host" placeholder="db.ejemplo.com o 10.0.0.1" class="h-8 text-xs" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="conn-database" class="flex items-center gap-1 text-xs">
                        <Database class="size-3" />
                        Base de datos
                    </Label>
                    <Input id="conn-database" v-model="form.database" placeholder="nombre_base_datos" class="h-8 text-xs" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <Label for="conn-user" class="flex items-center gap-1 text-xs">
                            <User class="size-3" />
                            Usuario
                        </Label>
                        <Input id="conn-user" v-model="form.username" placeholder="usuario" class="h-8 text-xs" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <Label for="conn-password" class="flex items-center gap-1 text-xs">
                            <Key class="size-3" />
                            Contraseña
                        </Label>
                        <Input id="conn-password" v-model="form.password" type="password" placeholder="••••••••" class="h-8 text-xs" />
                    </div>
                </div>

                <div class="flex items-center gap-6 py-1">
                    <div class="flex items-center gap-2">
                        <Checkbox id="conn-ssl" v-model:checked="form.ssl_enabled" />
                        <Label for="conn-ssl" class="flex items-center gap-1 text-xs">
                            <Shield class="size-3" />
                            SSL
                        </Label>
                    </div>

                    <div class="flex items-center gap-2">
                        <Label for="conn-max-rows" class="text-xs">Max filas</Label>
                        <Input id="conn-max-rows" v-model.number="form.max_rows" type="number" class="h-7 w-20 text-xs" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Label for="conn-timeout" class="text-xs">Timeout (s)</Label>
                        <Input id="conn-timeout" v-model.number="form.query_timeout_seconds" type="number" class="h-7 w-16 text-xs" />
                    </div>
                </div>

                <div
                    v-if="serverError"
                    class="rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive"
                >
                    {{ serverError }}
                </div>

                <div
                    v-if="testResult"
                    class="flex items-center gap-2 rounded-md px-3 py-2 text-xs"
                    :class="testResult.success ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-destructive/10 text-destructive'"
                >
                    <Check v-if="testResult.success" class="size-3.5" />
                    <Wifi v-else class="size-3.5" />
                    {{ testResult.message }}
                </div>
            </div>

            <DialogFooter class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isSubmitting"
                    @click="open = false"
                >
                    Cancelar
                </Button>
                <Button
                    size="sm"
                    :disabled="!canSave() || isSubmitting"
                    @click="handleSave"
                >
                    <Loader2 v-if="isSubmitting" class="size-3.5 animate-spin" data-icon="inline-start" />
                    <Plug v-else class="size-3.5" data-icon="inline-start" />
                    {{ isSubmitting ? 'Creando...' : 'Guardar conexión' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
