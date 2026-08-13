<script setup lang="ts">
import { ChevronLeft, ChevronRight, Search, Table2 } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import ErrorState from '@/components/SearchConsole/ErrorState.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber, formatPercent, formatPosition } from '@/lib/format';
import type { MetricsRow, SearchConsoleError } from '@/types/search-console';

const props = withDefaults(
    defineProps<{
        title: string;
        icon?: Component;
        rows: MetricsRow[] | null;
        loading?: boolean;
        error?: SearchConsoleError | null;
        onRetry?: () => void;
        emptyMessage: string;
        searchable?: boolean;
        firstColumnLabel: string;
        formatFirstColumn?: (key: string) => string;
        showBar?: boolean;
        page?: number;
        hasMore?: boolean;
        pageSize?: number;
        onPreviousPage?: () => void;
        onNextPage?: () => void;
        disabled?: boolean;
    }>(),
    {
        icon: Table2,
        loading: false,
        error: null,
        searchable: false,
        showBar: false,
        pageSize: 10,
        disabled: false,
    },
);

const search = defineModel<string>('search', { default: '' });

const visibleRows = computed(() => {
    const rows = props.rows ?? [];
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return rows;
    }

    return rows.filter((row) => row.key.toLowerCase().includes(term));
});

const maxClicks = computed(() =>
    Math.max(0, ...visibleRows.value.map((row) => row.clicks)),
);

function barWidth(clicks: number): number {
    return maxClicks.value === 0 ? 0 : (clicks / maxClicks.value) * 100;
}

function labelFor(key: string): string {
    return props.formatFirstColumn !== undefined ? props.formatFirstColumn(key) : key;
}

const startRow = computed(() => {
    if (props.page === undefined) {
        return 1;
    }

    return (props.page - 1) * props.pageSize + 1;
});

const endRow = computed(() =>
    props.page === undefined
        ? visibleRows.value.length
        : startRow.value + visibleRows.value.length - 1,
);
</script>

<template>
    <Card class="rounded-xl">
        <CardHeader class="flex flex-col gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
            <CardTitle class="flex items-center gap-2 text-base font-semibold">
                <component :is="icon" class="size-4 text-muted-foreground" />
                {{ title }}
                <Spinner
                    v-if="loading && rows !== null"
                    class="size-4 text-muted-foreground"
                />
            </CardTitle>
            <div v-if="searchable" class="relative">
                <Search
                    class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    :placeholder="`Search ${firstColumnLabel.toLowerCase()}s…`"
                    class="h-8 w-full pl-8 sm:w-56"
                />
            </div>
        </CardHeader>
        <CardContent class="px-0">
            <ErrorState v-if="error !== null && rows === null" :error="error" :on-retry="onRetry" compact />
            <template v-else>
                <div
                    class="overflow-x-auto px-4 pb-2"
                    :class="loading && rows !== null ? 'opacity-60' : ''"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="py-2 text-left font-medium text-muted-foreground">
                                    {{ firstColumnLabel }}
                                </th>
                                <th class="py-2 text-right font-medium text-muted-foreground">
                                    Clicks
                                </th>
                                <th class="py-2 text-right font-medium text-muted-foreground">
                                    Impressions
                                </th>
                                <th class="py-2 text-right font-medium text-muted-foreground">
                                    CTR
                                </th>
                                <th class="py-2 text-right font-medium text-muted-foreground">
                                    Position
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="loading && rows === null">
                                <tr
                                    v-for="index in 5"
                                    :key="index"
                                    class="border-b last:border-0"
                                >
                                    <td class="py-3 pr-4">
                                        <Skeleton class="h-4 w-full sm:w-56" />
                                    </td>
                                    <td class="py-3"><Skeleton class="ml-auto h-4 w-10" /></td>
                                    <td class="py-3"><Skeleton class="ml-auto h-4 w-10" /></td>
                                    <td class="py-3"><Skeleton class="ml-auto h-4 w-10" /></td>
                                    <td class="py-3"><Skeleton class="ml-auto h-4 w-10" /></td>
                                </tr>
                            </template>
                            <template v-else-if="visibleRows.length > 0">
                                <tr
                                    v-for="row in visibleRows"
                                    :key="row.key"
                                    class="border-b last:border-0"
                                >
                                    <td class="max-w-xs py-3 pr-4">
                                        <div class="flex items-center gap-3">
                                            <div class="truncate" :title="row.key">
                                                {{ labelFor(row.key) }}
                                            </div>
                                            <div
                                                v-if="showBar"
                                                class="h-1.5 w-24 overflow-hidden rounded-full bg-muted sm:w-32"
                                            >
                                                <div
                                                    class="h-full rounded-full bg-primary"
                                                    :style="{ width: `${barWidth(row.clicks)}%` }"
                                                />
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{ formatNumber(row.clicks) }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{ formatNumber(row.impressions) }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{ formatPercent(row.ctr) }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{ formatPosition(row.position) }}
                                    </td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr>
                                    <td
                                        colspan="5"
                                        class="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        {{ emptyMessage }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </CardContent>
        <CardFooter
            v-if="page !== undefined && error === null"
            class="flex items-center justify-between gap-2 border-t px-4 py-3"
        >
            <p class="text-xs text-muted-foreground">
                Rows {{ startRow }}–{{ endRow }}
            </p>
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="page <= 1 || disabled"
                    @click="onPreviousPage?.()"
                >
                    <ChevronLeft />
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!hasMore || disabled"
                    @click="onNextPage?.()"
                >
                    Next
                    <ChevronRight />
                </Button>
            </div>
        </CardFooter>
    </Card>
</template>
