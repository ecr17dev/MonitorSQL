<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Connection = {
    id: number;
    name: string;
    driver: 'mysql' | 'mariadb' | 'pgsql';
    host: string;
    port: number;
    database: string;
    username: string;
    ssl_enabled: boolean;
    is_active: boolean;
    max_rows: number;
    query_timeout_seconds: number;
    last_tested_at: string | null;
    created_at: string;
};

type TableSummary = {
    name: string;
    schema: string | null;
};

const props = defineProps<{
    connections: Connection[];
}>();

const testingConnectionId = ref<number | null>(null);
const editingConnectionId = ref<number | null>(null);
const loadingTablesConnectionId = ref<number | null>(null);
const loadingPreviewTableKey = ref('');
const tableMap = ref<Record<number, TableSummary[]>>({});
const previewRows = ref<Record<string, Array<Record<string, unknown>>>>({});
const requestError = ref<string | null>(null);

const editForm = reactive({
    name: '',
    driver: 'pgsql',
    host: '',
    port: 5432,
    database: '',
    username: '',
    password: '',
    ssl_enabled: false,
    is_active: true,
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

async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message = (typeof payload.message === 'string' && payload.message) || 'Request failed.';
        throw new Error(message);
    }

    return payload as T;
}

function beginEdit(connection: Connection): void {
    editingConnectionId.value = connection.id;
    editForm.name = connection.name;
    editForm.driver = connection.driver;
    editForm.host = connection.host;
    editForm.port = connection.port;
    editForm.database = connection.database;
    editForm.username = connection.username;
    editForm.password = '';
    editForm.ssl_enabled = connection.ssl_enabled;
    editForm.is_active = connection.is_active;
    editForm.max_rows = connection.max_rows;
    editForm.query_timeout_seconds = connection.query_timeout_seconds;
}

function cancelEdit(): void {
    editingConnectionId.value = null;
}

function saveEdit(): void {
    if (editingConnectionId.value === null) {
        return;
    }

    router.put(`/connections/${editingConnectionId.value}`, editForm as never, {
        preserveScroll: true,
        onSuccess: () => {
            editingConnectionId.value = null;
        },
    });
}

function deleteConnection(connectionId: number): void {
    const confirmed = window.confirm('Delete this connection?');

    if (!confirmed) {
        return;
    }

    router.delete(`/connections/${connectionId}`, {
        preserveScroll: true,
    });
}

async function testConnection(connectionId: number): Promise<void> {
    testingConnectionId.value = connectionId;

    try {
        await fetch(`/connections/${connectionId}/test`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        });
    } finally {
        testingConnectionId.value = null;
        router.reload({ only: ['connections'] });
    }
}

async function loadTables(connectionId: number): Promise<void> {
    loadingTablesConnectionId.value = connectionId;
    requestError.value = null;

    try {
        const payload = await getJson<{ tables: TableSummary[] }>(`/connections/${connectionId}/tables`);
        tableMap.value = {
            ...tableMap.value,
            [connectionId]: payload.tables,
        };
    } catch (error) {
        requestError.value = error instanceof Error ? error.message : 'Could not load tables.';
    } finally {
        loadingTablesConnectionId.value = null;
    }
}

async function previewTable(connectionId: number, tableName: string): Promise<void> {
    const key = `${connectionId}:${tableName}`;
    loadingPreviewTableKey.value = key;
    requestError.value = null;

    try {
        const payload = await getJson<{ rows: Array<Record<string, unknown>> }>(
            `/connections/${connectionId}/tables/${encodeURIComponent(tableName)}/preview`,
        );

        previewRows.value = {
            ...previewRows.value,
            [key]: payload.rows,
        };
    } catch (error) {
        requestError.value = error instanceof Error ? error.message : 'Could not preview table.';
    } finally {
        loadingPreviewTableKey.value = '';
    }
}
</script>

<template>
    <Head title="Connections" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card>
            <CardHeader>
                <CardTitle>Create Connection</CardTitle>
                <CardDescription>Store encrypted credentials and enforce read-only access policies.</CardDescription>
            </CardHeader>
            <CardContent>
                <Form action="/connections" method="post" class="grid gap-3 md:grid-cols-3" v-slot="{ processing, errors }">
                    <div class="space-y-2">
                        <Label for="name">Name</Label>
                        <Input id="name" name="name" required />
                        <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="driver">Driver</Label>
                        <select
                            id="driver"
                            name="driver"
                            class="border-input focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                        >
                            <option value="pgsql">PostgreSQL</option>
                            <option value="mysql">MySQL</option>
                            <option value="mariadb">MariaDB</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <Label for="host">Host</Label>
                        <Input id="host" name="host" required />
                    </div>

                    <div class="space-y-2">
                        <Label for="port">Port</Label>
                        <Input id="port" name="port" type="number" required />
                    </div>

                    <div class="space-y-2">
                        <Label for="database">Database</Label>
                        <Input id="database" name="database" required />
                    </div>

                    <div class="space-y-2">
                        <Label for="username">Username</Label>
                        <Input id="username" name="username" required />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label for="password">Password</Label>
                        <Input id="password" name="password" type="password" required />
                    </div>

                    <div class="space-y-2">
                        <Label for="max_rows">Max rows</Label>
                        <Input id="max_rows" name="max_rows" type="number" value="1000" required />
                    </div>

                    <div class="space-y-2">
                        <Label for="query_timeout_seconds">Query timeout (s)</Label>
                        <Input id="query_timeout_seconds" name="query_timeout_seconds" type="number" value="30" required />
                    </div>

                    <div class="flex items-center gap-2 md:col-span-3">
                        <input id="ssl_enabled" name="ssl_enabled" type="checkbox" value="1" class="size-4" />
                        <Label for="ssl_enabled">SSL enabled</Label>
                    </div>

                    <div class="md:col-span-3">
                        <Button :disabled="processing">Create connection</Button>
                    </div>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Configured Connections</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="requestError" class="text-sm text-destructive">{{ requestError }}</p>

                <div v-for="connection in connections" :key="connection.id" class="rounded-md border p-4">
                    <div v-if="editingConnectionId !== connection.id" class="space-y-2">
                        <p class="font-medium">{{ connection.name }} ({{ connection.driver }})</p>
                        <p class="text-xs text-muted-foreground">
                            {{ connection.host }}:{{ connection.port }}/{{ connection.database }} · max rows {{ connection.max_rows }} · timeout {{ connection.query_timeout_seconds }}s
                        </p>
                        <p class="text-xs text-muted-foreground">Last tested: {{ connection.last_tested_at ?? 'Never' }}</p>
                        <div class="flex flex-wrap gap-2">
                            <Button size="sm" variant="secondary" @click="beginEdit(connection)">Edit</Button>
                            <Button
                                size="sm"
                                variant="secondary"
                                :disabled="testingConnectionId === connection.id"
                                @click="testConnection(connection.id)"
                            >
                                Test
                            </Button>
                            <Button
                                size="sm"
                                variant="secondary"
                                :disabled="loadingTablesConnectionId === connection.id"
                                @click="loadTables(connection.id)"
                            >
                                Explore tables
                            </Button>
                            <Button size="sm" variant="destructive" @click="deleteConnection(connection.id)">Delete</Button>
                        </div>

                        <div v-if="(tableMap[connection.id] ?? []).length > 0" class="space-y-2 pt-2">
                            <p class="text-sm font-medium">Visible tables</p>
                            <div class="grid gap-2 md:grid-cols-2">
                                <div v-for="table in tableMap[connection.id]" :key="table.name" class="rounded border p-2">
                                    <p class="text-xs">{{ table.schema ? `${table.schema}.` : '' }}{{ table.name }}</p>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        class="mt-2"
                                        :disabled="loadingPreviewTableKey === `${connection.id}:${table.name}`"
                                        @click="previewTable(connection.id, table.name)"
                                    >
                                        Preview
                                    </Button>

                                    <div v-if="(previewRows[`${connection.id}:${table.name}`] ?? []).length > 0" class="mt-2 overflow-x-auto">
                                        <table class="w-full text-left text-xs">
                                            <thead>
                                                <tr class="border-b">
                                                    <th
                                                        v-for="column in Object.keys(previewRows[`${connection.id}:${table.name}`][0])"
                                                        :key="column"
                                                        class="p-1"
                                                    >
                                                        {{ column }}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(row, rowIndex) in previewRows[`${connection.id}:${table.name}`]"
                                                    :key="rowIndex"
                                                    class="border-b"
                                                >
                                                    <td
                                                        v-for="(value, columnName) in row"
                                                        :key="`${columnName}-${String(value)}`"
                                                        class="p-1"
                                                    >
                                                        {{ value }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="grid gap-2 md:grid-cols-2">
                        <Input v-model="editForm.name" placeholder="Name" />
                        <Input v-model="editForm.host" placeholder="Host" />
                        <Input v-model.number="editForm.port" type="number" placeholder="Port" />
                        <Input v-model="editForm.database" placeholder="Database" />
                        <Input v-model="editForm.username" placeholder="Username" />
                        <Input v-model="editForm.password" placeholder="Password (optional)" type="password" />
                        <Input v-model.number="editForm.max_rows" type="number" placeholder="Max rows" />
                        <Input v-model.number="editForm.query_timeout_seconds" type="number" placeholder="Timeout" />
                        <div class="md:col-span-2 flex gap-2">
                            <Button size="sm" @click="saveEdit">Save</Button>
                            <Button size="sm" variant="secondary" @click="cancelEdit">Cancel</Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
