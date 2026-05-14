import { ref } from 'vue';

export type ChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    timestamp: Date;
};

export type MixedContent = {
    type: 'text' | 'sql_card';
    text?: string;
    sql?: string;
    explanation?: string;
    confidence?: string;
    tables_used?: string[];
    requires_confirmation?: boolean;
    suggested_visualization?: {
        type: string;
        x_axis: string | null;
        y_axis: string | null;
        reason: string;
    };
};

export function useChat() {
    const messages = ref<ChatMessage[]>([
        {
            id: 1,
            role: 'assistant' as const,
            content: 'Hola, soy MonitorSQL. Pregúntame sobre tus datos en lenguaje natural y generaré SQL seguro para ti.',
            timestamp: new Date(),
        },
    ]);

    const input = ref('');
    const isLoading = ref(false);
    const requestError = ref<string | null>(null);
    const conversationId = ref<string | null>(null);

    function pushMessage(role: ChatMessage['role'], content: string): ChatMessage {
        const msg: ChatMessage = {
            id: Date.now() + Math.floor(Math.random() * 1000),
            role,
            content,
            timestamp: new Date(),
        };
        messages.value.push(msg);
        return msg;
    }

    function clearError() {
        requestError.value = null;
    }

    function setError(message: string) {
        requestError.value = message;
    }

    function reset() {
        messages.value = [];
        input.value = '';
        isLoading.value = false;
        requestError.value = null;
        conversationId.value = null;
    }

    return {
        messages,
        input,
        isLoading,
        requestError,
        conversationId,
        pushMessage,
        clearError,
        setError,
        reset,
    };
}
