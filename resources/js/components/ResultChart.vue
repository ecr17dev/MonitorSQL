<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    ArcElement,
} from 'chart.js';
import type { QueryResult } from '@/composables/useQueryExecution';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
);

const props = defineProps<{
    result: QueryResult;
    chartConfig?: {
        type: string;
        x_axis: string | null;
        y_axis: string | null;
    };
}>();

const containerRef = ref<HTMLElement | null>(null);

const chartType = computed(() => {
    const type = props.chartConfig?.type ?? 'table';
    if (['bar', 'line', 'donut'].includes(type)) {
        return type;
    }
    return null;
});

const numericColumns = computed(() => {
    if (!props.result.columns.length || !props.result.rows.length) return [];
    return props.result.columns.filter((col) => {
        const sample = props.result.rows[0]?.[col.name];
        return typeof sample === 'number';
    });
});

const labelColumn = computed(() => {
    if (props.chartConfig?.x_axis) {
        const found = props.result.columns.find((c) => c.name === props.chartConfig?.x_axis);
        if (found) return found.name;
    }
    for (const col of props.result.columns) {
        const sample = props.result.rows[0]?.[col.name];
        if (typeof sample === 'string') return col.name;
    }
    return props.result.columns[0]?.name ?? '';
});

const valueColumn = computed(() => {
    if (props.chartConfig?.y_axis) {
        const found = props.result.columns.find((c) => c.name === props.chartConfig?.y_axis);
        if (found) return found.name;
    }
    return numericColumns.value[0]?.name ?? '';
});

const labels = computed(() => {
    return props.result.rows.map((row) => String(row[labelColumn.value] ?? ''));
});

const dataset = computed(() => {
    return {
        labels: labels.value,
        datasets: [
            {
                label: valueColumn.value,
                data: props.result.rows.map((row) => {
                    const val = row[valueColumn.value];
                    return typeof val === 'number' ? val : 0;
                }),
                backgroundColor: [
                    'hsl(var(--chart-1) / 0.7)',
                    'hsl(var(--chart-2) / 0.7)',
                    'hsl(var(--chart-3) / 0.7)',
                    'hsl(var(--chart-4) / 0.7)',
                    'hsl(var(--chart-5) / 0.7)',
                ],
                borderColor: [
                    'hsl(var(--chart-1))',
                    'hsl(var(--chart-2))',
                    'hsl(var(--chart-3))',
                    'hsl(var(--chart-4))',
                    'hsl(var(--chart-5))',
                ],
            },
        ],
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
    },
    scales:
        chartType.value === 'donut'
            ? undefined
            : {
                  y: {
                      beginAtZero: true,
                  },
              },
}));
</script>

<template>
    <Card v-if="chartType && result.rows.length > 0">
        <CardHeader class="pb-2">
            <CardTitle class="text-sm font-medium">
                {{ chartType === 'bar' ? 'Gráfico de Barras' : chartType === 'donut' ? 'Gráfico Donut' : 'Gráfico de Líneas' }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div ref="containerRef" class="h-72 w-full">
                <Bar v-if="chartType === 'bar'" :data="dataset" :options="chartOptions" />
                <Line v-else-if="chartType === 'line'" :data="dataset" :options="chartOptions" />
                <Doughnut v-else-if="chartType === 'donut'" :data="dataset" :options="chartOptions" />
            </div>
        </CardContent>
    </Card>
    <div v-else class="flex items-center justify-center rounded-md border py-12">
        <p class="text-sm text-muted-foreground">
            {{ result.rows.length === 0 ? 'Sin datos para graficar.' : 'No hay un tipo de gráfico compatible con estos datos.' }}
        </p>
    </div>
</template>
