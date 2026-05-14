<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Send, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import ChatMessage from '@/components/ChatMessage.vue';
import SqlCard from '@/components/SqlCard.vue';
import SqlEditor from '@/components/SqlEditor.vue';
import ResultTabs from '@/components/ResultTabs.vue';
import ContextPanel from '@/components/ContextPanel.vue';
import ModeToggle from '@/components/ModeToggle.vue';
import { useChat } from '@/composables/useChat';
import {
    useSqlGeneration,
    type AiGeneratedSql,
} from '@/composables/useSqlGeneration';
import { useQueryExecution, type QueryResult } from '@/composables/useQueryExecution';
import { useContext, type Connection } from '@/composables/useContext';
import { chat as chatRoute } from '@/routes';

type ConnectionProp = Connection;

type StoredChatState = {
    messages: Array<{
        id: number;
        role: 'user' | 'assistant';
        content: string;
        timestamp: string;
    }>;
    input: string;
    conversationId: string | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Chat',
                href: chatRoute(),
            },
        ],
    },
});

const props = defineProps<{
    connections: ConnectionProp[];
}>();

const page = usePage();
const authPermissions = computed<string[]>(() => {
    const auth = page.props.auth as { permissions?: string[] } | undefined;
    return auth?.permissions ?? [];
});

const canUseAi = computed(() => authPermissions.value.includes('queries.ai_generate'));
const canExecuteQueries = computed(() => authPermissions.value.includes('queries.execute'));
const canExport = computed(() => authPermissions.value.includes('queries.export'));

const chat = useChat();
const sqlGen = useSqlGeneration();
const queryExec = useQueryExecution();
const context = useContext(props.connections);

const manualSql = ref('SELECT 1 AS status');
const messagesContainer = ref<HTMLElement | null>(null);
const chatStoragePrefix = 'monitorsql.chat.v1';

const authUserId = computed<number | null>(() => {
    const auth = page.props.auth as { user?: { id?: number } } | undefined;
    return auth?.user?.id ?? null;
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

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message =
            (typeof body.message === 'string' && body.message) ||
            'Request failed. Verify SQL, permissions, and selected connection.';
        throw new Error(message);
    }

    return body as T;
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

function currentStorageKey(): string | null {
    if (authUserId.value === null || context.selectedConnectionId.value === null) {
        return null;
    }

    return `${chatStoragePrefix}:${authUserId.value}:${context.selectedConnectionId.value}`;
}

function saveChatState(): void {
    const key = currentStorageKey();

    if (key === null) {
        return;
    }

    const payload: StoredChatState = {
        messages: chat.messages.value.map((message) => ({
            id: message.id,
            role: message.role,
            content: message.content,
            timestamp: message.timestamp.toISOString(),
        })),
        input: chat.input.value,
        conversationId: chat.conversationId.value,
    };

    localStorage.setItem(key, JSON.stringify(payload));
}

function loadChatState(): void {
    const key = currentStorageKey();

    if (key === null) {
        return;
    }

    const raw = localStorage.getItem(key);

    if (!raw) {
        return;
    }

    try {
        const parsed = JSON.parse(raw) as StoredChatState;
        chat.messages.value = (parsed.messages ?? []).map((message) => ({
            id: message.id,
            role: message.role,
            content: message.content,
            timestamp: new Date(message.timestamp),
        }));
        chat.input.value = parsed.input ?? '';
        chat.conversationId.value = parsed.conversationId ?? null;
    } catch {
        // Ignore malformed local state and keep defaults.
    }
}

function clearCurrentChatState(): void {
    const key = currentStorageKey();

    if (key !== null) {
        localStorage.removeItem(key);
    }
}

function pushInitialAssistantGreeting(): void {
    if (chat.messages.value.length === 0) {
        chat.pushMessage(
            'assistant',
            'Hola, soy MonitorSQL. Pregúntame sobre tus datos en lenguaje natural y generaré SQL seguro para ti.',
        );
    }
}

async function sendPrompt(promptText: string): Promise<AiGeneratedSql | null> {
    if (!promptText.trim()) {
        return null;
    }

    if (!canUseAi.value) {
        chat.pushMessage('assistant', 'No tienes permiso para generar consultas con IA.');
        return null;
    }

    if (context.selectedConnectionId.value === null) {
        chat.pushMessage(
            'assistant',
            'Selecciona una conexión y base de datos antes de preguntar.',
        );
        return null;
    }

    chat.isLoading.value = true;
    chat.clearError();

    try {
        const payload = await postJson<{
            sql: string;
            explanation: string;
            tables_used: string[];
            confidence: string;
            conversation_id: string | null;
            dialect: 'mysql' | 'mariadb' | 'pgsql';
            memory_applied: {
                short_term: boolean;
                long_term: boolean;
            };
            adaptation_note?: string;
            requires_confirmation?: boolean;
            suggested_visualization: {
                type: string;
                x_axis: string | null;
                y_axis: string | null;
                reason: string;
            };
        }>('/queries/ai-generate', {
            connection_id: context.selectedConnectionId.value,
            question: promptText,
            conversation_id: chat.conversationId.value,
            selected_tables: context.selectedTables.value,
        });

        const generated: AiGeneratedSql = {
            sql: payload.sql,
            explanation: payload.explanation,
            tables_used: payload.tables_used,
            confidence: payload.confidence,
            requires_confirmation: payload.requires_confirmation ?? true,
            suggested_visualization: payload.suggested_visualization,
        };

        sqlGen.setGenerated(generated);
        manualSql.value = payload.sql;
        chat.conversationId.value = payload.conversation_id;

        const adaptationMessage = payload.adaptation_note
            ? `${payload.adaptation_note} (dialecto: ${payload.dialect}).`
            : `Consulta adaptada al dialecto ${payload.dialect}.`;

        chat.pushMessage(
            'assistant',
            `He generado esta consulta SQL para responder a tu pregunta. ${adaptationMessage} Revísala y ejecútala cuando estés listo.`,
        );

        return generated;
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Error al generar SQL.';
        sqlGen.setError(message);
        chat.pushMessage('assistant', `No pude generar la consulta: ${message}`);
        return null;
    } finally {
        chat.isLoading.value = false;
        scrollToBottom();
    }
}

async function handleSend() {
    const text = chat.input.value.trim();
    if (!text) return;

    chat.pushMessage('user', text);
    chat.input.value = '';
    scrollToBottom();

    await sendPrompt(text);
}

async function executeQuery(sql: string): Promise<void> {
    if (!canExecuteQueries.value) {
        chat.setError('No tienes permiso para ejecutar consultas.');
        return;
    }

    if (context.selectedConnectionId.value === null) {
        chat.setError('Selecciona una conexión primero.');
        return;
    }

    queryExec.isLoading.value = true;
    queryExec.clearError();

    try {
        const result = await postJson<QueryResult>('/queries/execute', {
            connection_id: context.selectedConnectionId.value,
            sql,
            is_ai_generated: !!sqlGen.generated.value,
        });

        queryExec.setResult(result, sql);

        chat.pushMessage(
            'assistant',
            `Consulta ejecutada: ${result.meta.row_count} filas en ${result.meta.duration_ms}ms. Puedes ver los resultados abajo, pedir un resumen, ordenar, filtrar o exportar.`,
        );
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Error al ejecutar la consulta.';
        queryExec.setError(message);
        chat.pushMessage('assistant', `Error al ejecutar: ${message}`);
    } finally {
        queryExec.isLoading.value = false;
        scrollToBottom();
    }
}

function handleSuggestionSelect(prompt: string) {
    chat.input.value = prompt;
}

async function handleExport(format: 'csv' | 'xlsx' | 'json') {
    if (!canExport.value) {
        chat.setError('No tienes permiso para exportar.');
        return;
    }

    if (context.selectedConnectionId.value === null) {
        return;
    }

    try {
        await postJson<{ message: string }>('/exports', {
            connection_id: context.selectedConnectionId.value,
            sql: queryExec.executedSql.value,
            format,
        });
        chat.pushMessage(
            'assistant',
            `Exportación a ${format.toUpperCase()} en cola. Recibirás una notificación cuando esté lista.`,
        );
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Error al encolar exportación.';
        chat.pushMessage('assistant', `Error al exportar: ${message}`);
    }
}

function handleClearChat() {
    clearCurrentChatState();
    chat.reset();
    sqlGen.clearGenerated();
    queryExec.clearResult();
    pushInitialAssistantGreeting();
}

onMounted(() => {
    loadChatState();
    pushInitialAssistantGreeting();
    scrollToBottom();
});

watch(
    () => context.selectedConnectionId.value,
    () => {
        chat.reset();
        sqlGen.clearGenerated();
        queryExec.clearResult();
        loadChatState();
        pushInitialAssistantGreeting();
        scrollToBottom();
    },
);

watch(
    [
        () => chat.messages.value,
        () => chat.input.value,
        () => chat.conversationId.value,
        () => context.selectedConnectionId.value,
    ],
    () => {
        saveChatState();
    },
    { deep: true },
);
</script>

<template>
    <Head title="Chat" />

    <div class="flex h-full flex-1 flex-col">
        <ResizablePanelGroup direction="horizontal" class="h-full">
            <ResizablePanel :default-size="70" :min-size="40">
                <div class="flex h-full flex-col">
                    <div class="flex items-center justify-between border-b px-4 py-2">
                        <div class="flex items-center gap-3">
                            <h2 class="text-sm font-semibold">Chat SQL</h2>
                            <ModeToggle :mode="context.userMode.value" @toggle="context.toggleMode()" />
                        </div>
                        <div class="flex items-center gap-2">
                            <Button variant="ghost" size="sm" @click="handleClearChat">
                                <Trash2 class="size-3.5" data-icon="inline-start" />
                                Limpiar
                            </Button>
                        </div>
                    </div>

                    <div
                        ref="messagesContainer"
                        class="flex-1 overflow-y-auto p-4 flex flex-col gap-3"
                    >
                        <Card v-if="props.connections.length === 0" class="border-dashed">
                            <CardHeader>
                                <CardTitle class="text-sm">Sin conexiones configuradas</CardTitle>
                                <CardDescription>
                                    Ve al menú Connections en el sidebar para agregar una base de datos y empezar a consultar.
                                </CardDescription>
                            </CardHeader>
                        </Card>

                        <template v-for="msg in chat.messages.value" :key="msg.id">
                            <ChatMessage :role="msg.role" :content="msg.content" />
                        </template>

                        <div v-if="sqlGen.generated.value" class="pl-4">
                            <SqlCard
                                :generated="sqlGen.generated.value"
                                :is-executing="queryExec.isLoading.value"
                                :show-sql="context.userMode.value === 'analyst'"
                                @execute="executeQuery(sqlGen.generated.value?.sql ?? '')"
                                @edit="(sql: string) => { manualSql = sql; }"
                                @copy="() => {}"
                                @save="() => {}"
                                @explain="() => {}"
                            />
                        </div>

                        <div v-if="context.userMode.value === 'analyst' && !sqlGen.generated.value" class="flex flex-col gap-2 px-4">
                            <SqlEditor
                                v-model="manualSql"
                                :disabled="queryExec.isLoading.value"
                                @execute="executeQuery(manualSql)"
                            />
                            <Button
                                size="sm"
                                class="self-end"
                                :disabled="queryExec.isLoading.value || !canExecuteQueries"
                                @click="executeQuery(manualSql)"
                            >
                                Ejecutar SQL
                            </Button>
                        </div>

                        <div v-if="queryExec.result.value" class="rounded-lg border bg-card p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-muted-foreground">
                                        {{ queryExec.result.value.meta.row_count }} filas &middot;
                                        {{ queryExec.result.value.meta.duration_ms }}ms
                                    </span>
                                    <span
                                        v-if="queryExec.result.value.meta.limited"
                                        class="text-[10px] text-amber-600 font-medium"
                                    >
                                        LIMITADO
                                    </span>
                                </div>
                            </div>
                            <ResultTabs
                                :result="queryExec.result.value"
                                :sql="queryExec.executedSql.value"
                                :ai-generated="sqlGen.generated.value"
                                :connection-id="context.selectedConnectionId.value ?? 0"
                                @export-csv="handleExport('csv')"
                                @export-xlsx="handleExport('xlsx')"
                                @export-json="handleExport('json')"
                            />
                        </div>

                        <div
                            v-if="chat.isLoading.value"
                            class="flex items-center gap-2 pl-4 text-sm text-muted-foreground"
                        >
                            <span class="inline-block size-2 animate-pulse rounded-full bg-primary" />
                            Generando consulta SQL...
                        </div>
                    </div>

                    <div class="border-t p-3">
                        <div v-if="!props.connections.length" class="mb-2">
                            <p class="text-xs text-muted-foreground">
                                Sin conexiones. Crea una en <strong>Connections</strong> para empezar.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Textarea
                                v-model="chat.input.value"
                                placeholder="Pregunta sobre tus datos... (ej: muéstrame las ventas de este mes por día)"
                                :disabled="chat.isLoading.value"
                                class="min-h-10 flex-1 resize-none"
                                rows="1"
                                @keydown.enter.prevent="handleSend()"
                            />
                            <Button
                                size="icon"
                                :disabled="chat.isLoading.value || !chat.input.value.trim()"
                                @click="handleSend()"
                            >
                                <Send class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </ResizablePanel>

            <ResizableHandle />

            <ResizablePanel :default-size="30" :min-size="20" :max-size="40">
                <ContextPanel
                    :connections="props.connections"
                    :selected-connection-id="context.selectedConnectionId.value"
                    :selected-tables="context.selectedTables.value"
                    @select-connection="context.selectConnection"
                    @toggle-table="context.toggleTable"
                    @select-suggestion="handleSuggestionSelect"
                    @new-chat="handleClearChat"
                />
            </ResizablePanel>
        </ResizablePanelGroup>
    </div>
</template>
