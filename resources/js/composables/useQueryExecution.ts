import { ref } from 'vue';

export type QueryResultColumn = {
    name: string;
    type: string;
};

export type QueryResultMeta = {
    duration_ms: number;
    row_count: number;
    limited: boolean;
};

export type QueryResult = {
    columns: QueryResultColumn[];
    rows: Array<Record<string, unknown>>;
    meta: QueryResultMeta;
};

export function useQueryExecution() {
    const result = ref<QueryResult | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const executedSql = ref<string>('');

    function setResult(data: QueryResult, sql: string) {
        result.value = data;
        executedSql.value = sql;
    }

    function clearResult() {
        result.value = null;
        executedSql.value = '';
        error.value = null;
    }

    function setError(message: string) {
        error.value = message;
    }

    function clearError() {
        error.value = null;
    }

    return {
        result,
        isLoading,
        error,
        executedSql,
        setResult,
        clearResult,
        setError,
        clearError,
    };
}
