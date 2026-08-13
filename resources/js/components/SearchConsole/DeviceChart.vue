<script setup lang="ts">
import { Monitor } from '@lucide/vue';
import type { ChartData, ChartOptions, TooltipItem } from 'chart.js';
import { computed } from 'vue';
import ErrorState from '@/components/SearchConsole/ErrorState.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Doughnut } from '@/lib/chart';
import { deviceLabel, formatNumber, formatPercent } from '@/lib/format';
import type { MetricsRow, SearchConsoleError } from '@/types/search-console';

const props = withDefaults(
    defineProps<{
        rows: MetricsRow[] | null;
        loading?: boolean;
        error?: SearchConsoleError | null;
        onRetry?: () => void;
    }>(),
    {
        loading: false,
        error: null,
    },
);

const COLORS = ['#2563eb', '#16a34a', '#d97706', '#9333ea', '#dc2626'];

const chartData = computed<ChartData<'doughnut'>>(() => {
    const rows = props.rows ?? [];

    return {
        labels: rows.map((row) => deviceLabel(row.key)),
        datasets: [
            {
                data: rows.map((row) => row.clicks),
                backgroundColor: rows.map((_, index) => COLORS[index % COLORS.length]),
                borderWidth: 2,
                borderColor: 'hsl(var(--card))',
            },
        ],
    };
});

const totalClicks = computed(() =>
    (props.rows ?? []).reduce((sum, row) => sum + row.clicks, 0),
);

function shareOf(row: MetricsRow): number {
    return totalClicks.value === 0 ? 0 : row.clicks / totalClicks.value;
}

const chartOptions = computed<ChartOptions<'doughnut'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (item: TooltipItem<'doughnut'>) => {
                    const value = item.parsed;

                    return `${formatNumber(value)} clicks (${formatPercent(
                        totalClicks.value === 0 ? 0 : value / totalClicks.value,
                    )})`;
                },
            },
        },
    },
}));
</script>

<template>
    <Card class="rounded-xl">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-semibold">
                <Monitor class="size-4 text-muted-foreground" />
                Devices
            </CardTitle>
        </CardHeader>
        <CardContent>
            <ErrorState v-if="error !== null && rows === null" :error="error" :on-retry="onRetry" compact />
            <div v-else class="grid gap-6 sm:grid-cols-2 sm:items-center">
                <div class="relative h-48 sm:h-56">
                    <Skeleton v-if="loading && rows === null" class="size-full rounded-full" />
                    <Doughnut
                        v-else-if="rows !== null && rows.length > 0"
                        :data="chartData"
                        :options="chartOptions"
                    />
                    <div
                        v-else
                        class="flex size-full items-center justify-center text-sm text-muted-foreground"
                    >
                        No device data for this period.
                    </div>
                </div>
                <ul
                    class="space-y-3"
                    :class="loading && rows !== null ? 'opacity-60' : ''"
                >
                    <li
                        v-for="(row, index) in rows ?? []"
                        :key="row.key"
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: COLORS[index % COLORS.length] }"
                            />
                            <span class="truncate font-medium">
                                {{ deviceLabel(row.key) }}
                            </span>
                        </span>
                        <span class="flex shrink-0 items-center gap-3 tabular-nums">
                            <span class="text-muted-foreground">{{ formatPercent(shareOf(row)) }}</span>
                            <span class="w-14 text-right">{{ formatNumber(row.clicks) }}</span>
                        </span>
                    </li>
                    <li
                        v-if="rows !== null && rows.length === 0 && !loading"
                        class="text-sm text-muted-foreground"
                    >
                        No device data for this period.
                    </li>
                </ul>
            </div>
        </CardContent>
    </Card>
</template>
