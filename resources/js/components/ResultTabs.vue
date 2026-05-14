<script setup lang="ts">
import { BarChart3, Download, FileJson, FileSpreadsheet, FileText, MessageSquare, Table2, Terminal } from 'lucide-vue-next';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import type { AiGeneratedSql } from '@/composables/useSqlGeneration';
import type { QueryResult } from '@/composables/useQueryExecution';
import ResultTable from '@/components/ResultTable.vue';
import ResultChart from '@/components/ResultChart.vue';
import ResultSummary from '@/components/ResultSummary.vue';

const props = defineProps<{
    result: QueryResult;
    sql: string;
    aiGenerated?: AiGeneratedSql | null;
    connectionId: number;
}>();

const emit = defineEmits<{
    exportCsv: [];
    exportXlsx: [];
    exportJson: [];
}>();
</script>

<template>
    <Tabs defaultValue="table" class="w-full">
        <div class="flex items-center justify-between border-b pb-2">
            <TabsList>
                <TabsTrigger value="table">
                    <Table2 class="size-3.5" data-icon="inline-start" />
                    Tabla
                </TabsTrigger>
                <TabsTrigger value="chart">
                    <BarChart3 class="size-3.5" data-icon="inline-start" />
                    Gráfico
                </TabsTrigger>
                <TabsTrigger value="summary">
                    <MessageSquare class="size-3.5" data-icon="inline-start" />
                    Resumen IA
                </TabsTrigger>
                <TabsTrigger value="sql">
                    <Terminal class="size-3.5" data-icon="inline-start" />
                    SQL
                </TabsTrigger>
                <TabsTrigger value="export">
                    <Download class="size-3.5" data-icon="inline-start" />
                    Exportar
                </TabsTrigger>
            </TabsList>
        </div>

        <TabsContent value="table" class="mt-3">
            <ResultTable :result="result" />
        </TabsContent>

        <TabsContent value="chart" class="mt-3">
            <ResultChart
                :result="result"
                :chart-config="aiGenerated?.suggested_visualization ?? undefined"
            />
        </TabsContent>

        <TabsContent value="summary" class="mt-3">
            <ResultSummary :result="result" :sql="sql" :connection-id="connectionId" />
        </TabsContent>

        <TabsContent value="sql" class="mt-3">
            <pre class="overflow-x-auto rounded-md border bg-muted/30 p-4 text-xs font-mono"><code>{{ sql }}</code></pre>
        </TabsContent>

        <TabsContent value="export" class="mt-3">
            <div class="flex flex-col gap-3">
                <p class="text-sm text-muted-foreground">
                    Exporta {{ result.meta.row_count }} filas en el formato que prefieras.
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" @click="emit('exportCsv')">
                        <FileText class="size-3.5" data-icon="inline-start" />
                        CSV
                    </Button>
                    <Button variant="outline" size="sm" @click="emit('exportXlsx')">
                        <FileSpreadsheet class="size-3.5" data-icon="inline-start" />
                        Excel
                    </Button>
                    <Button variant="outline" size="sm" @click="emit('exportJson')">
                        <FileJson class="size-3.5" data-icon="inline-start" />
                        JSON
                    </Button>
                </div>
                <p class="text-[10px] text-muted-foreground">
                    La exportación se procesa en segundo plano. Recibirás una notificación cuando esté lista.
                </p>
            </div>
        </TabsContent>
    </Tabs>
</template>
