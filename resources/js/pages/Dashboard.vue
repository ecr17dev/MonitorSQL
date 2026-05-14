<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type Connection = {
    id: number;
    name: string;
    driver: string;
    host: string;
    database: string;
    is_active: boolean;
    max_rows: number;
};

type QueryRun = {
    id: number;
    status: string;
    rows_returned: number;
    duration_ms: number;
    created_at: string;
    sql: string;
};

type AuditLog = {
    id: number;
    action: string;
    status: string;
    created_at: string;
    user?: { id: number; name: string; email: string };
    connection?: { id: number; name: string };
};

type DataExport = {
    id: number;
    format: string;
    status: string;
    row_count: number;
    created_at: string;
    expires_at: string | null;
};

type QueryResult = {
    columns: Array<{ name: string; type: string }>;
    rows: Array<Record<string, unknown>>;
    meta: {
        duration_ms: number;
        row_count: number;
        limited: boolean;
    };
};

type PaginatedResponse<T> = {
    data: T[];
};

type ChatMessage = {
    id: number;
    role: 'assistant' | 'user';
    content: string;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<{
    connections: Connection[];
    recentQueryRuns: QueryRun[];
    recentAudits: AuditLog[];
}>();

const selectedConnectionId = ref<number | null>(props.connections[0]?.id ?? null);
const manualSql = ref('SELECT 1 AS status');
const selectedFormat = ref<'csv' | 'xlsx' | 'json'>('csv');
const queryResult = ref<QueryResult | null>(null);
const generatedSql = ref('');
const generatedExplanation = ref('');
const generatedConfidence = ref('');
const generatedTables = ref<string[]>([]);
const generatedRequiresConfirmation = ref(true);
const requestError = ref<string | null>(null);
const isLoading = ref(false);

const exportsData = ref<DataExport[]>([]);
const exportsStatusFilter = ref('');
const auditData = ref<AuditLog[]>([]);
const auditActionFilter = ref('');
const auditStatusFilter = ref('');
const page = usePage();

const chatInput = ref('');
const chatMessages = ref<ChatMessage[]>([
    {
        id: 1,
        role: 'assistant',
        content: 'Tip 1: selecciona una conexión y pide una consulta en lenguaje natural.',
    },
    {
        id: 2,
        role: 'assistant',
        content: 'Tip 2: revisa el SQL generado, confirma y luego ejecútalo para ver resultados.',
    },
]);

const tipPrompts = [
    'Muestrame las primeras 10 filas de la tabla principal',
    'Dame un conteo por fecha de creacion de la ultima semana',
    'Que tabla recomiendas explorar primero para validar datos',
];

const permissions = computed<string[]>(() => {
    const auth = page.props.auth as { permissions?: string[] } | undefined;

    return auth?.permissions ?? [];
});

const canExecuteQueries = computed(() => permissions.value.includes('queries.execute'));
const canUseAi = computed(() => permissions.value.includes('queries.ai_generate'));
const canExport = computed(() => permissions.value.includes('queries.export'));
const canViewAudit = computed(() => permissions.value.includes('audit.view'));
const hasConnections = computed(() => props.connections.length > 0);

const activeConnection = computed(() => {
    return props.connections.find((connection) => connection.id === selectedConnectionId.value) ?? null;
});

function getXsrfToken(): string {
    const token = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return token ? decodeURIComponent(token) : '';
}

async function postJson<T>(url: string, payload: Record<string, unknown>): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const responseBody = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message =
            (typeof responseBody.message === 'string' && responseBody.message) ||
            'Request failed. Verify SQL, permissions, and selected connection.';

        throw new Error(message);
    }

    return responseBody as T;
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

    const responseBody = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message =
            (typeof responseBody.message === 'string' && responseBody.message) ||
            'Unable to load data from server.';

        throw new Error(message);
    }

    return responseBody as T;
}

function pushChatMessage(role: ChatMessage['role'], content: string): void {
    chatMessages.value.push({
        id: Date.now() + Math.floor(Math.random() * 1000),
        role,
        content,
    });
}

async function generateAiSql(question: string): Promise<void> {
    if (!canUseAi.value) {
        requestError.value = 'You do not have permission to generate AI SQL.';
        return;
    }

    if (selectedConnectionId.value === null) {
        requestError.value = 'Select a connection first.';
        return;
    }

    isLoading.value = true;
    requestError.value = null;

    try {
        const payload = await postJson<{
            sql: string;
            explanation: string;
            tables_used: string[];
            confidence: string;
            requires_confirmation?: boolean;
        }>('/queries/ai-generate', {
            connection_id: selectedConnectionId.value,
            question,
        });

        generatedSql.value = payload.sql;
        generatedExplanation.value = payload.explanation;
        generatedConfidence.value = payload.confidence;
        generatedTables.value = payload.tables_used;
        generatedRequiresConfirmation.value = payload.requires_confirmation ?? true;
        manualSql.value = payload.sql;
    } catch (error) {
        requestError.value = error instanceof Error ? error.message : 'AI generation failed.';
        throw error;
    } finally {
        isLoading.value = false;
    }
}

async function sendChatPrompt(): Promise<void> {
    if (chatInput.value.trim() === '') {
        return;
    }

    const question = chatInput.value.trim();
    pushChatMessage('user', question);
    chatInput.value = '';

    if (!hasConnections.value || selectedConnectionId.value === null) {
        pushChatMessage(
            'assistant',
            'No hay conexiones activas. Ve al item "Connections" del sidebar para cargar acceso a una base de datos.',
        );
        return;
    }

    try {
        await generateAiSql(question);

        pushChatMessage(
            'assistant',
            `SQL sugerido:\n${generatedSql.value}\n\nConfianza: ${generatedConfidence.value}\nTablas: ${generatedTables.value.join(', ') || 'N/A'}\n${generatedExplanation.value}`,
        );
    } catch (error) {
        const message = error instanceof Error ? error.message : 'No fue posible generar SQL.';
        pushChatMessage('assistant', message);
    }
}

async function runQuery(isAiGenerated = false): Promise<void> {
    if (!canExecuteQueries.value) {
        requestError.value = 'You do not have permission to execute queries.';
        return;
    }

    if (selectedConnectionId.value === null) {
        requestError.value = 'Select a connection first.';
        return;
    }

    if (isAiGenerated && generatedRequiresConfirmation.value) {
        const confirmed = window.confirm('Execute AI generated SQL after validation?');

        if (!confirmed) {
            return;
        }
    }

    isLoading.value = true;
    requestError.value = null;

    try {
        queryResult.value = await postJson<QueryResult>('/queries/execute', {
            connection_id: selectedConnectionId.value,
            sql: manualSql.value,
            is_ai_generated: isAiGenerated,
        });

        await Promise.all([
            canExport.value ? loadExports() : Promise.resolve(),
            canViewAudit.value ? loadAudits() : Promise.resolve(),
        ]);
    } catch (error) {
        requestError.value = error instanceof Error ? error.message : 'Query execution failed.';
    } finally {
        isLoading.value = false;
    }
}

async function queueExport(): Promise<void> {
    if (!canExport.value) {
        requestError.value = 'You do not have permission to create exports.';
        return;
    }

    if (selectedConnectionId.value === null) {
        requestError.value = 'Select a connection first.';
        return;
    }

    isLoading.value = true;
    requestError.value = null;

    try {
        await postJson<{ message: string }>('/exports', {
            connection_id: selectedConnectionId.value,
            sql: manualSql.value,
            format: selectedFormat.value,
        });

        await Promise.all([
            canExport.value ? loadExports() : Promise.resolve(),
            canViewAudit.value ? loadAudits() : Promise.resolve(),
        ]);
    } catch (error) {
        requestError.value = error instanceof Error ? error.message : 'Export queue failed.';
    } finally {
        isLoading.value = false;
    }
}

function downloadExport(exportId: number): void {
    window.location.href = `/exports/${exportId}/download`;
}

async function loadExports(): Promise<void> {
    const params = new URLSearchParams();

    if (exportsStatusFilter.value !== '') {
        params.set('status', exportsStatusFilter.value);
    }

    const payload = await getJson<PaginatedResponse<DataExport>>(`/exports?${params.toString()}`);

    exportsData.value = payload.data;
}

async function loadAudits(): Promise<void> {
    const params = new URLSearchParams();

    if (auditActionFilter.value !== '') {
        params.set('action', auditActionFilter.value);
    }

    if (auditStatusFilter.value !== '') {
        params.set('status', auditStatusFilter.value);
    }

    const payload = await getJson<PaginatedResponse<AuditLog>>(`/audit?${params.toString()}`);

    auditData.value = payload.data;
}

function applyTipPrompt(prompt: string): void {
    chatInput.value = prompt;
}

onMounted(async () => {
    if (!hasConnections.value) {
        pushChatMessage('assistant', 'Empieza por crear una conexión en el sidebar. Sin eso no puedo consultar la base.');
    }

    try {
        await Promise.all([
            canExport.value ? loadExports() : Promise.resolve(),
            canViewAudit.value ? loadAudits() : Promise.resolve(),
        ]);
    } catch (error) {
        requestError.value = error instanceof Error ? error.message : 'Unable to load dashboard data.';
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <Card>
            <CardHeader>
                <CardTitle>Chat IA de arranque</CardTitle>
                <CardDescription>
                    Haz preguntas en lenguaje natural para generar SQL seguro y validar flujo end-to-end.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-if="!hasConnections" class="rounded-md border border-dashed p-3 text-sm">
                    <p class="text-muted-foreground">
                        No hay conexiones configuradas. Ve al item <strong>Connections</strong> del sidebar para cargar base de datos y accesos.
                    </p>
                    <Button class="mt-3" as-child>
                        <Link href="/connections">Ir a Connections</Link>
                    </Button>
                </div>

                <div class="max-h-72 space-y-2 overflow-y-auto rounded-md border p-3">
                    <div
                        v-for="message in chatMessages"
                        :key="message.id"
                        class="rounded-md border p-2"
                        :class="message.role === 'assistant' ? 'bg-muted/40' : 'bg-background'"
                    >
                        <p class="text-xs text-muted-foreground">{{ message.role === 'assistant' ? 'IA' : 'Tú' }}</p>
                        <p class="text-sm whitespace-pre-wrap">{{ message.content }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="prompt in tipPrompts"
                        :key="prompt"
                        variant="outline"
                        class="cursor-pointer"
                        @click="applyTipPrompt(prompt)"
                    >
                        {{ prompt }}
                    </Badge>
                </div>

                <div class="flex gap-2">
                    <Input
                        v-model="chatInput"
                        placeholder="Pregúntame por una consulta SQL segura"
                        :disabled="isLoading || !canUseAi"
                        @keyup.enter="sendChatPrompt"
                    />
                    <Button :disabled="isLoading || !canUseAi" @click="sendChatPrompt">Enviar</Button>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Connection</CardTitle>
                    <CardDescription>Choose the SQL source for validation and execution.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <Label for="connection">Database connection</Label>
                    <select
                        id="connection"
                        v-model.number="selectedConnectionId"
                        class="border-input focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                    >
                        <option v-for="connection in connections" :key="connection.id" :value="connection.id">
                            {{ connection.name }} · {{ connection.driver }}
                        </option>
                    </select>
                    <p v-if="activeConnection" class="text-xs text-muted-foreground">
                        {{ activeConnection.host }}/{{ activeConnection.database }} · max rows {{ activeConnection.max_rows }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Export</CardTitle>
                    <CardDescription>Queue export from validated SQL results.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <Label for="format">Format</Label>
                    <select
                        id="format"
                        v-model="selectedFormat"
                        class="border-input focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                    >
                        <option value="csv">CSV</option>
                        <option value="xlsx">XLSX</option>
                        <option value="json">JSON</option>
                    </select>
                    <Button :disabled="isLoading || !canExport" @click="queueExport">Queue Export</Button>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>SQL Workspace</CardTitle>
                <CardDescription>Manual SQL and AI SQL are validated before execution.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <textarea
                    v-model="manualSql"
                    class="border-input focus-visible:ring-ring/50 min-h-36 w-full rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                />
                <p v-if="generatedSql" class="text-xs text-muted-foreground">
                    SQL generado por IA (confianza: {{ generatedConfidence }}).
                </p>
                <div class="flex gap-2">
                    <Button :disabled="isLoading || !canExecuteQueries" @click="runQuery(false)">Execute SQL</Button>
                    <Button :disabled="isLoading || !canUseAi || !canExecuteQueries" variant="secondary" @click="runQuery(true)">Execute Generated SQL</Button>
                </div>
                <p v-if="requestError" class="text-sm text-destructive">{{ requestError }}</p>
            </CardContent>
        </Card>

        <Card v-if="queryResult">
            <CardHeader>
                <CardTitle>Results</CardTitle>
                <CardDescription>
                    {{ queryResult.meta.row_count }} rows · {{ queryResult.meta.duration_ms }} ms
                    <span v-if="queryResult.meta.limited"> · limited</span>
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b">
                                <th v-for="column in queryResult.columns" :key="column.name" class="p-2">{{ column.name }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, rowIndex) in queryResult.rows" :key="rowIndex" class="border-b">
                                <td v-for="column in queryResult.columns" :key="column.name" class="p-2">
                                    {{ row[column.name] }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div id="query-history" class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Recent Query Runs</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-2 text-sm">
                        <li v-for="run in recentQueryRuns" :key="run.id" class="rounded-md border p-2">
                            <p>{{ run.status }} · {{ run.rows_returned }} rows · {{ run.duration_ms }} ms</p>
                            <p class="truncate text-xs text-muted-foreground">{{ run.sql }}</p>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Export Queue</CardTitle>
                    <CardDescription>Track state and download completed files.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Label for="export-status" class="text-xs">Status</Label>
                        <select
                            id="export-status"
                            v-model="exportsStatusFilter"
                            class="border-input focus-visible:ring-ring/50 rounded-md border bg-transparent px-2 py-1 text-xs outline-none focus-visible:ring-[3px]"
                        >
                            <option value="">All</option>
                            <option value="pending">pending</option>
                            <option value="completed">completed</option>
                            <option value="failed">failed</option>
                            <option value="expired">expired</option>
                        </select>
                        <Button size="sm" variant="secondary" @click="loadExports">Apply</Button>
                    </div>
                    <ul class="space-y-2 text-sm">
                        <li v-for="item in exportsData" :key="item.id" class="rounded-md border p-2">
                            <p>#{{ item.id }} · {{ item.format.toUpperCase() }} · {{ item.status }} · {{ item.row_count }} rows</p>
                            <p class="text-xs text-muted-foreground">{{ item.created_at }}</p>
                            <Button
                                v-if="item.status === 'completed'"
                                size="sm"
                                class="mt-2"
                                variant="secondary"
                                @click="downloadExport(item.id)"
                            >
                                Download
                            </Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <Card v-if="canViewAudit" id="audit">
            <CardHeader>
                <CardTitle>Audit Events</CardTitle>
                <CardDescription>Filter by action and status.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-2 md:grid-cols-4">
                    <Input v-model="auditActionFilter" placeholder="Action" />
                    <Input v-model="auditStatusFilter" placeholder="Status" />
                    <Button variant="secondary" @click="loadAudits">Apply Filters</Button>
                </div>
                <ul class="space-y-2 text-sm">
                    <li v-for="audit in auditData" :key="audit.id" class="rounded-md border p-2">
                        <p>{{ audit.action }} · {{ audit.status }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ audit.user?.name ?? 'N/A' }} · {{ audit.connection?.name ?? 'No connection' }} · {{ audit.created_at }}
                        </p>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
