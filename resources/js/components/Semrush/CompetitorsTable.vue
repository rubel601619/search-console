<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import ErrorState from '@/components/Semrush/ErrorState.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatCurrency, formatNumber } from '@/lib/format';
import type {
    CompetitorRow,
    PaginatedResult,
    SemrushError,
} from '@/types/semrush';

const props = withDefaults(
    defineProps<{
        rows: PaginatedResult<CompetitorRow> | null;
        loading?: boolean;
        error?: SemrushError | null;
        onRetry?: () => void;
        page?: number;
        hasMore?: boolean;
        onPreviousPage?: () => void;
        onNextPage?: () => void;
        disabled?: boolean;
        sort?: string;
        onSort?: (column: string) => void;
    }>(),
    {
        loading: false,
        error: null,
        disabled: false,
        sort: '',
    },
);

const SORTABLE = new Set([
    'relevance',
    'common_keywords',
    'organic_keywords',
    'organic_traffic',
]);

const visibleRows = computed(() => props.rows?.rows ?? []);

function sortDirection(column: string): 'asc' | 'desc' | null {
    if (props.sort === column) {
        return 'asc';
    }

    if (props.sort === `-${column}`) {
        return 'desc';
    }

    return null;
}

const startRow = computed(() => {
    if (props.page === undefined) {
        return 1;
    }

    return (props.page - 1) * (props.rows?.limit ?? 20) + 1;
});

const endRow = computed(() =>
    props.page === undefined
        ? visibleRows.value.length
        : startRow.value + visibleRows.value.length - 1,
);

function headerClass(column: string): string {
    return SORTABLE.has(column)
        ? 'cursor-pointer select-none hover:text-foreground'
        : '';
}
</script>

<template>
    <Card class="rounded-xl">
        <CardHeader class="flex items-center gap-2 space-y-0">
            <CardTitle class="flex items-center gap-2 text-base font-semibold">
                <Users class="size-4 text-muted-foreground" />
                Organic competitors
                <Spinner
                    v-if="loading && rows !== null"
                    class="size-4 text-muted-foreground"
                />
            </CardTitle>
        </CardHeader>
        <CardContent class="px-0">
            <ErrorState
                v-if="error !== null && rows === null"
                :error="error"
                :on-retry="onRetry"
                compact
            />
            <template v-else>
                <div
                    class="overflow-x-auto px-4 pb-2"
                    :class="loading && rows !== null ? 'opacity-60' : ''"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-muted-foreground">
                                <th class="py-2 text-left font-medium">
                                    Domain
                                </th>
                                <th
                                    class="py-2 text-right font-medium"
                                    :class="headerClass('relevance')"
                                    @click="onSort?.('relevance')"
                                >
                                    <span
                                        class="inline-flex items-center justify-end gap-0.5"
                                    >
                                        Relevance
                                        <ArrowUp
                                            v-if="
                                                sortDirection('relevance') ===
                                                'asc'
                                            "
                                            class="size-3"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortDirection('relevance') ===
                                                'desc'
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="py-2 text-right font-medium"
                                    :class="headerClass('common_keywords')"
                                    @click="onSort?.('common_keywords')"
                                >
                                    <span
                                        class="inline-flex items-center justify-end gap-0.5"
                                    >
                                        Common keywords
                                        <ArrowUp
                                            v-if="
                                                sortDirection(
                                                    'common_keywords',
                                                ) === 'asc'
                                            "
                                            class="size-3"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortDirection(
                                                    'common_keywords',
                                                ) === 'desc'
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="py-2 text-right font-medium"
                                    :class="headerClass('organic_keywords')"
                                    @click="onSort?.('organic_keywords')"
                                >
                                    <span
                                        class="inline-flex items-center justify-end gap-0.5"
                                    >
                                        Keywords
                                        <ArrowUp
                                            v-if="
                                                sortDirection(
                                                    'organic_keywords',
                                                ) === 'asc'
                                            "
                                            class="size-3"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortDirection(
                                                    'organic_keywords',
                                                ) === 'desc'
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="py-2 text-right font-medium"
                                    :class="headerClass('organic_traffic')"
                                    @click="onSort?.('organic_traffic')"
                                >
                                    <span
                                        class="inline-flex items-center justify-end gap-0.5"
                                    >
                                        Traffic
                                        <ArrowUp
                                            v-if="
                                                sortDirection(
                                                    'organic_traffic',
                                                ) === 'asc'
                                            "
                                            class="size-3"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortDirection(
                                                    'organic_traffic',
                                                ) === 'desc'
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                </th>
                                <th class="py-2 text-right font-medium">
                                    Organic cost
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
                                        <Skeleton class="h-4 w-full sm:w-40" />
                                    </td>
                                    <td class="py-3">
                                        <Skeleton class="ml-auto h-4 w-10" />
                                    </td>
                                    <td class="py-3">
                                        <Skeleton class="ml-auto h-4 w-10" />
                                    </td>
                                    <td class="py-3">
                                        <Skeleton class="ml-auto h-4 w-10" />
                                    </td>
                                    <td class="py-3">
                                        <Skeleton class="ml-auto h-4 w-10" />
                                    </td>
                                    <td class="py-3">
                                        <Skeleton class="ml-auto h-4 w-10" />
                                    </td>
                                </tr>
                            </template>
                            <template v-else-if="visibleRows.length > 0">
                                <tr
                                    v-for="row in visibleRows"
                                    :key="row.domain"
                                    class="border-b last:border-0"
                                >
                                    <td class="max-w-xs py-3 pr-4">
                                        <a
                                            :href="`https://${row.domain}`"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="truncate font-medium text-primary hover:underline"
                                        >
                                            {{ row.domain }}
                                        </a>
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.relevance !== null
                                                ? row.relevance
                                                : '—'
                                        }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.common_keywords !== null
                                                ? formatNumber(
                                                      row.common_keywords,
                                                  )
                                                : '—'
                                        }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.organic_keywords !== null
                                                ? formatNumber(
                                                      row.organic_keywords,
                                                  )
                                                : '—'
                                        }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.organic_traffic !== null
                                                ? formatNumber(
                                                      row.organic_traffic,
                                                  )
                                                : '—'
                                        }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.organic_cost !== null
                                                ? formatCurrency(
                                                      row.organic_cost,
                                                  )
                                                : '—'
                                        }}
                                    </td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr>
                                    <td
                                        colspan="6"
                                        class="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No organic competitors found for this
                                        domain.
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
