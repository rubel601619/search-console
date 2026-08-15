<script setup lang="ts">
import { SearchCheck } from '@lucide/vue';
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js';
import { computed } from 'vue';
import ErrorState from '@/components/Semrush/ErrorState.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Line } from '@/lib/chart';
import { formatCompact, formatCurrency, formatNumber } from '@/lib/format';
import type { KeywordOverview, SemrushError } from '@/types/semrush';

const props = withDefaults(
    defineProps<{
        data: KeywordOverview | null;
        loading?: boolean;
        error?: SemrushError | null;
        onRetry?: () => void;
    }>(),
    {
        loading: false,
        error: null,
    },
);

const monthLabels = computed(() => {
    const count = props.data?.trends.length ?? 12;
    const labels: string[] = [];
    const now = new Date();

    for (let i = count - 1; i >= 0; i--) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1);

        labels.push(date.toLocaleString('en-US', { month: 'short' }));
    }

    return labels;
});

const chartData = computed<ChartData<'line'>>(() => ({
    labels: monthLabels.value,
    datasets: [
        {
            label: 'Search volume',
            data: props.data?.trends ?? [],
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

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (item: TooltipItem<'line'>) =>
                    formatCompact(item.parsed.y ?? 0),
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
            beginAtZero: true,
            ticks: {
                callback: (value: number | string) =>
                    formatCompact(Number(value)),
            },
        },
    },
}));

const stats = computed(() => {
    if (props.data === null) {
        return [];
    }

    const entries: { label: string; value: string }[] = [
        {
            label: 'Volume',
            value:
                props.data.volume !== null
                    ? formatNumber(props.data.volume)
                    : '—',
        },
        {
            label: 'CPC',
            value:
                props.data.cpc !== null ? formatCurrency(props.data.cpc) : '—',
        },
        {
            label: 'Difficulty',
            value:
                props.data.difficulty !== null
                    ? String(props.data.difficulty)
                    : '—',
        },
        {
            label: 'Competition',
            value:
                props.data.competition !== null
                    ? props.data.competition.toFixed(2)
                    : '—',
        },
        {
            label: 'Results',
            value:
                props.data.results !== null
                    ? formatNumber(props.data.results)
                    : '—',
        },
        { label: 'Intent', value: props.data.intent ?? '—' },
    ];

    return entries;
});

const isLoading = computed(() => props.loading && props.data === null);
</script>

<template>
    <Card class="rounded-xl">
        <CardHeader
            class="flex flex-row items-center justify-between space-y-0"
        >
            <CardTitle class="flex items-center gap-2 text-base font-semibold">
                <SearchCheck class="size-4 text-muted-foreground" />
                Keyword overview
                <span
                    v-if="data !== null"
                    class="max-w-56 truncate text-sm font-normal text-muted-foreground"
                >
                    {{ data.keyword }}
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent>
            <ErrorState
                v-if="error !== null && data === null"
                :error="error"
                :on-retry="onRetry"
                compact
            />
            <template v-else>
                <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-6">
                    <div
                        v-for="stat in stats"
                        :key="stat.label"
                        class="rounded-lg border p-3"
                    >
                        <Skeleton v-if="isLoading" class="h-6 w-16" />
                        <template v-else>
                            <p class="text-xs text-muted-foreground">
                                {{ stat.label }}
                            </p>
                            <p
                                class="mt-1 truncate text-lg font-semibold tabular-nums"
                            >
                                {{ stat.value }}
                            </p>
                        </template>
                    </div>
                </div>
                <div class="mt-4">
                    <Skeleton v-if="isLoading" class="h-48 w-full" />
                    <div
                        v-else-if="data !== null && data.trends.length > 0"
                        class="h-48"
                    >
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>
            </template>
        </CardContent>
    </Card>
</template>
