<script setup lang="ts">
import { Gauge, Globe, Search, TrendingUp } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCompact, formatCurrency, formatNumber } from '@/lib/format';
import type { DomainOverview } from '@/types/semrush';

const props = defineProps<{
    overview: DomainOverview | null;
    loading?: boolean;
}>();

type MetricKey =
    'rank' | 'organic_keywords' | 'organic_traffic' | 'organic_cost';

type MetricDef = {
    key: MetricKey;
    label: string;
    icon: Component;
};

type MetricCard = MetricDef & {
    value: string;
};

const metrics: MetricDef[] = [
    { key: 'rank', label: 'Global rank', icon: Globe },
    { key: 'organic_keywords', label: 'Organic keywords', icon: Search },
    { key: 'organic_traffic', label: 'Organic traffic', icon: TrendingUp },
    { key: 'organic_cost', label: 'Organic cost', icon: Gauge },
];

const isLoading = computed(() => props.loading && props.overview === null);

function valueFor(metric: MetricKey): string {
    if (props.overview === null) {
        return '—';
    }

    switch (metric) {
        case 'rank':
            return props.overview.rank !== null
                ? formatNumber(props.overview.rank)
                : '—';
        case 'organic_keywords':
            return props.overview.organic_keywords !== null
                ? formatNumber(props.overview.organic_keywords)
                : '—';
        case 'organic_traffic':
            return props.overview.organic_traffic !== null
                ? formatCompact(props.overview.organic_traffic)
                : '—';
        case 'organic_cost':
            return props.overview.organic_cost !== null
                ? formatCurrency(props.overview.organic_cost)
                : '—';
    }
}

const cards = computed<MetricCard[]>(() =>
    metrics.map((metric) => ({
        ...metric,
        value: valueFor(metric.key),
    })),
);
</script>

<template>
    <div
        class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        :class="loading && overview !== null ? 'opacity-60' : ''"
    >
        <Card v-for="card in cards" :key="card.key" class="rounded-xl">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    {{ card.label }}
                </CardTitle>
                <component
                    :is="card.icon"
                    class="size-4 text-muted-foreground"
                />
            </CardHeader>
            <CardContent>
                <Skeleton v-if="isLoading" class="h-8 w-24" />
                <div v-else class="text-2xl font-bold tabular-nums">
                    {{ card.value }}
                </div>
            </CardContent>
        </Card>
    </div>
</template>
