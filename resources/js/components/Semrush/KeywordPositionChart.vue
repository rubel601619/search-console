<script setup lang="ts">
import type { ChartData, ChartOptions } from 'chart.js';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Bar } from '@/lib/chart';
import { formatNumber } from '@/lib/format';
import type { PositionBucket } from '@/types/semrush';

const props = defineProps<{
    buckets: PositionBucket[] | null;
    loading?: boolean;
}>();

const chartData = computed<ChartData<'bar'>>(() => ({
    labels: (props.buckets ?? []).map((bucket) => bucket.label),
    datasets: [
        {
            label: 'Keywords',
            data: (props.buckets ?? []).map((bucket) => bucket.value),
            backgroundColor: '#2563eb',
            borderRadius: 4,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        x: {
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
                callback: (value: number | string) =>
                    formatNumber(Number(value)),
            },
        },
    },
}));

const hasData = computed(() =>
    (props.buckets ?? []).some((bucket) => bucket.value > 0),
);
</script>

<template>
    <Card class="rounded-xl">
        <CardHeader>
            <CardTitle class="text-base font-semibold">
                Keyword position distribution
            </CardTitle>
        </CardHeader>
        <CardContent>
            <Skeleton v-if="loading && buckets === null" class="h-64 w-full" />
            <div v-else-if="hasData" class="h-64">
                <Bar :data="chartData" :options="chartOptions" />
            </div>
            <div
                v-else
                class="flex h-64 items-center justify-center text-sm text-muted-foreground"
            >
                No position data available for this domain.
            </div>
        </CardContent>
    </Card>
</template>
