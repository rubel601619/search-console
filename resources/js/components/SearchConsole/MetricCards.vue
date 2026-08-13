<script setup lang="ts">
import { ArrowDown, ArrowUp, Eye, MousePointerClick, Search, TrendingUp } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatChange, formatCompact, formatPercent, formatPosition } from '@/lib/format';
import type { MetricsComparison, MetricsTotals } from '@/types/search-console';

const props = defineProps<{
    totals: MetricsTotals | null;
    comparison: MetricsComparison | null;
    loading?: boolean;
}>();

type MetricKey = 'clicks' | 'impressions' | 'ctr' | 'position';

type MetricDef = {
    key: MetricKey;
    label: string;
    icon: Component;
    invertPositive?: boolean;
};

type MetricCard = MetricDef & {
    value: string;
    change: number | null;
    changeText: string | null;
    up: boolean;
    down: boolean;
    good: boolean;
};

const metrics: MetricDef[] = [
    { key: 'clicks', label: 'Clicks', icon: MousePointerClick },
    { key: 'impressions', label: 'Impressions', icon: Eye },
    { key: 'ctr', label: 'CTR', icon: TrendingUp },
    { key: 'position', label: 'Avg. position', icon: Search, invertPositive: true },
];

const isLoading = computed(() => props.loading && props.totals === null);

function valueFor(metric: MetricKey): string {
    if (props.totals === null) {
        return '—';
    }

    switch (metric) {
        case 'clicks':
            return formatCompact(props.totals.clicks);
        case 'impressions':
            return formatCompact(props.totals.impressions);
        case 'ctr':
            return formatPercent(props.totals.ctr);
        case 'position':
            return formatPosition(props.totals.position);
    }
}

const cards = computed<MetricCard[]>(() =>
    metrics.map((metric) => {
        const change = props.comparison?.[metric.key] ?? null;

        return {
            ...metric,
            value: valueFor(metric.key),
            change,
            changeText: formatChange(change),
            up: change !== null && change > 0,
            down: change !== null && change < 0,
            good: change !== null && (metric.invertPositive === true ? change < 0 : change > 0),
        };
    }),
);
</script>

<template>
    <div
        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        :class="props.loading && props.totals !== null ? 'opacity-60' : ''"
    >
        <Card v-for="card in cards" :key="card.key" class="rounded-xl">
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    {{ card.label }}
                </CardTitle>
                <component :is="card.icon" class="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <Skeleton v-if="isLoading" class="h-8 w-24" />
                <div v-else class="flex flex-col gap-1">
                    <div class="text-2xl font-bold tabular-nums">
                        {{ card.value }}
                    </div>
                    <span
                        v-if="card.change !== null"
                        :class="[
                            'inline-flex w-fit items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-medium',
                            card.good
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                : 'bg-destructive/10 text-destructive',
                        ]"
                    >
                        <ArrowUp v-if="card.up" class="size-3" />
                        <ArrowDown v-else-if="card.down" class="size-3" />
                        {{ card.changeText }}
                    </span>
                    <span v-else class="text-xs text-muted-foreground">No previous data</span>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
