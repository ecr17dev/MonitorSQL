<script setup lang="ts">
import { computed } from 'vue';
import type { QueryResult } from '@/composables/useQueryExecution';

const props = defineProps<{
    result: QueryResult;
}>();

const columns = computed(() => props.result.columns);
const rows = computed(() => props.result.rows);

function formatCell(value: unknown): string {
    if (value === null) {
        return 'NULL';
    }
    if (value === undefined) {
        return '';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }
    return String(value);
}

function isNull(value: unknown): boolean {
    return value === null;
}
</script>

<template>
    <div class="overflow-x-auto rounded-md border">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b bg-muted/50">
                    <th
                        v-for="column in columns"
                        :key="column.name"
                        class="px-3 py-2 text-xs font-medium text-muted-foreground"
                    >
                        <div class="flex flex-col">
                            <span>{{ column.name }}</span>
                            <span class="text-[10px] font-normal">{{ column.type }}</span>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, rowIndex) in rows"
                    :key="rowIndex"
                    class="border-b transition-colors hover:bg-muted/50"
                    :class="rowIndex % 2 === 0 ? 'bg-background' : 'bg-muted/20'"
                >
                    <td
                        v-for="column in columns"
                        :key="column.name"
                        class="max-w-64 truncate px-3 py-2 text-xs"
                        :class="{ 'text-muted-foreground italic': isNull(row[column.name]) }"
                        :title="formatCell(row[column.name])"
                    >
                        {{ formatCell(row[column.name]) }}
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="result.meta.limited" class="border-t bg-muted/30 px-3 py-2 text-[10px] text-muted-foreground">
            Los resultados fueron limitados. Se muestran {{ result.meta.row_count }} filas.
        </div>
    </div>
</template>
