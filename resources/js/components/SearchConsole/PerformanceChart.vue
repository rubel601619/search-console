<script setup lang="ts">
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Line } from '@/lib/chart';
import { formatCompact, formatNumber, formatPercent, formatPosition } from '@/lib/format';
import type { MetricsRow } from '@/types/search-console';

const props = defineProps<{
    rows: MetricsRow[] | null;
    loading?: boolean;
}>();

type MetricKey = 'clicks' | 'impressions' | 'ctr' | 'position';

const metricOptions: { key: MetricKey; label: string; beginAtZero: boolean }[] = [
    { key: 'clicks', label: 'Clicks', beginAtZero: true },
    { key: 'impressions', label: 'Impressions', beginAtZero: true },
    { key: 'ctr', label: 'CTR', beginAtZero: true },
    { key: 'position', label: 'Position', beginAtZero: false },
];

const metric = ref<MetricKey>('clicks');

const activeOption = computed(
    () => metricOptions.find((option) => option.key === metric.value) ?? metricOptions[0],
);

const chartData = computed<ChartData<'line'>>(() => ({
    labels: (props.rows ?? []).map((row) => row.key),
    datasets: [
        {
            label: activeOption.value.label,
            data: (props.rows ?? []).map((row) => row[metric.value]),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            fill: true,
            tension: 0.3,
            pointRadius: 0,
            pointHitRadius: 8,
            borderWidth: 2,
        },
    ],
}));

function formatTick(value: number): string {
    switch (metric.value) {
        case 'clicks':
        case 'impressions':
            return formatCompact(value);
        case 'ctr':
            return `${(value * 100).toFixed(0)}%`;
        case 'position':
            return value.toFixed(0);
    }
}

function formatTooltip(value: number): string {
    switch (metric.value) {
        case 'clicks':
            return `${formatNumber(value)} clicks`;
        case 'impressions':
            return `${formatNumber(value)} impressions`;
        case 'ctr':
            return formatPercent(value);
        case 'position':
            return formatPosition(value);
    }
}

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                title: (items: TooltipItem<'line'>[]) => items[0]?.label ?? '',
                label: (item: TooltipItem<'line'>) => formatTooltip(item.parsed.y ?? 0),
            },
        },
    },
    scales: {
        x: {
            ticks: {
                maxTicksLimit: 8,
                maxRotation: 0,
                autoSkip: true,
            },
            grid: { display: false },
        },
        y: {
            beginAtZero: activeOption.value.beginAtZero,
            ticks: {
                callback: (value: number | string) => formatTick(Number(value)),
            },
        },
    },
}));
</script>

<template>
    <Card class="rounded-xl">
        <CardHeader class="flex flex-col gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
            <CardTitle class="text-base font-semibold">Performance</CardTitle>
            <div class="flex flex-wrap items-center gap-1">
                <Button
                    v-for="option in metricOptions"
                    :key="option.key"
                    :variant="metric === option.key ? 'default' : 'outline'"
                    size="sm"
                    @click="metric = option.key"
                >
                    {{ option.label }}
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <Skeleton v-if="loading && rows === null" class="h-64 w-full" />
            <div v-else-if="rows !== null && rows.length > 0" class="h-64">
                <Line :data="chartData" :options="chartOptions" />
            </div>
            <div
                v-else
                class="flex h-64 items-center justify-center text-sm text-muted-foreground"
            >
                No performance data for this period.
            </div>
        </CardContent>
    </Card>
</template>
