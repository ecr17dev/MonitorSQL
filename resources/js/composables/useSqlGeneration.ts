import { ref } from 'vue';

export type AiGeneratedSql = {
    sql: string;
    explanation: string;
    tables_used: string[];
    confidence: string;
    requires_confirmation: boolean;
    suggested_visualization: {
        type: string;
        x_axis: string | null;
        y_axis: string | null;
        reason: string;
    };
};

export function useSqlGeneration() {
    const generated = ref<AiGeneratedSql | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    function setGenerated(data: AiGeneratedSql) {
        generated.value = data;
    }

    function clearGenerated() {
        generated.value = null;
        error.value = null;
    }

    function setError(message: string) {
        error.value = message;
    }

    function clearError() {
        error.value = null;
    }

    return {
        generated,
        isLoading,
        error,
        setGenerated,
        clearGenerated,
        setError,
        clearError,
    };
}
