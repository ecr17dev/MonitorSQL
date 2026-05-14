import { computed, ref } from 'vue';

export type Connection = {
    id: number;
    name: string;
    driver: string;
    host: string;
    database: string;
    is_active: boolean;
    max_rows: number;
};

export type TableSummary = {
    name: string;
    schema: string | null;
};

export function useContext(connections: Connection[]) {
    const selectedConnectionId = ref<number | null>(connections[0]?.id ?? null);
    const userMode = ref<'simple' | 'analyst'>('simple');
    const selectedTables = ref<string[]>([]);

    const activeConnection = computed(() => {
        return connections.find((c) => c.id === selectedConnectionId.value) ?? null;
    });

    function selectConnection(id: number | null) {
        selectedConnectionId.value = id;
        selectedTables.value = [];
    }

    function toggleMode() {
        userMode.value = userMode.value === 'simple' ? 'analyst' : 'simple';
    }

    function setTables(tables: string[]) {
        selectedTables.value = tables;
    }

    function toggleTable(table: string) {
        const index = selectedTables.value.indexOf(table);
        if (index === -1) {
            selectedTables.value.push(table);
        } else {
            selectedTables.value.splice(index, 1);
        }
    }

    return {
        selectedConnectionId,
        userMode,
        selectedTables,
        activeConnection,
        selectConnection,
        toggleMode,
        setTables,
        toggleTable,
    };
}
