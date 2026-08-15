<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    Search,
    TrendingUp,
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
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatCompact, formatNumber } from '@/lib/format';
import type {
    OrganicKeywordRow,
    PaginatedResult,
    SemrushError,
} from '@/types/semrush';

const props = withDefaults(
    defineProps<{
        rows: PaginatedResult<OrganicKeywordRow> | null;
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

const emit = defineEmits<{
    'select-keyword': [keyword: string];
}>();

const search = defineModel<string>('search', { default: '' });

const SORTABLE = new Set([
    'position',
    'volume',
    'traffic',
    'traffic_percent',
    'difficulty',
    'competition',
]);

const visibleRows = computed(() => {
    const rows = props.rows?.rows ?? [];
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return rows;
    }

    return rows.filter((row) => row.keyword.toLowerCase().includes(term));
});

function sortDirection(column: string): 'asc' | 'desc' | null {
    if (props.sort === column) {
        return 'asc';
    }

    if (props.sort === `-${column}`) {
        return 'desc';
    }

    return null;
}

function sparklinePoints(trends: number[]): string | null {
    if (trends.length < 2) {
        return null;
    }

    const width = 72;
    const height = 20;
    const min = Math.min(...trends);
    const max = Math.max(...trends);
    const range = max - min || 1;

    return trends
        .map((value, index) => {
            const x = (index / (trends.length - 1)) * width;
            const y = height - ((value - min) / range) * (height - 4) - 2;

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
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
        <CardHeader
            class="flex flex-col gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between"
        >
            <CardTitle class="flex items-center gap-2 text-base font-semibold">
                <TrendingUp class="size-4 text-muted-foreground" />
                Organic keywords
                <Spinner
                    v-if="loading && rows !== null"
                    class="size-4 text-muted-foreground"
                />
            </CardTitle>
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search keywords…"
                    class="h-8 w-full pl-8 sm:w-56"
                />
            </div>
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
                                <th
                                    class="py-2 text-left font-medium"
                                    :class="headerClass('keyword')"
                                >
                                    Keyword
                                </th>
                                <th
                                    class="py-2 text-right font-medium"
                                    :class="headerClass('position')"
                                    @click="onSort?.('position')"
                                >
                                    <span
                                        class="inline-flex items-center justify-end gap-0.5"
                                    >
                                        Position
                                        <ArrowUp
                                            v-if="
                                                sortDirection('position') ===
                                                'asc'
                                            "
                                            class="size-3"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortDirection('position') ===
                                                'desc'
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="py-2 text-right font-medium"
                                    :class="headerClass('volume')"
                                    @click="onSort?.('volume')"
                                >
                                    <span
                                        class="inline-flex items-center justify-end gap-0.5"
                                    >
                                        Volume
                                        <ArrowUp
                                            v-if="
                                                sortDirection('volume') ===
                                                'asc'
                                            "
                                            class="size-3"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortDirection('volume') ===
                                                'desc'
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="py-2 text-right font-medium"
                                    :class="headerClass('traffic')"
                                    @click="onSort?.('traffic')"
                                >
                                    <span
                                        class="inline-flex items-center justify-end gap-0.5"
                                    >
                                        Traffic
                                        <ArrowUp
                                            v-if="
                                                sortDirection('traffic') ===
                                                'asc'
                                            "
                                            class="size-3"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortDirection('traffic') ===
                                                'desc'
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                </th>
                                <th
                                    class="py-2 text-right font-medium"
                                    :class="headerClass('traffic_percent')"
                                    @click="onSort?.('traffic_percent')"
                                >
                                    <span
                                        class="inline-flex items-center justify-end gap-0.5"
                                    >
                                        Traffic %
                                        <ArrowUp
                                            v-if="
                                                sortDirection(
                                                    'traffic_percent',
                                                ) === 'asc'
                                            "
                                            class="size-3"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortDirection(
                                                    'traffic_percent',
                                                ) === 'desc'
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                </th>
                                <th class="py-2 text-right font-medium">CPC</th>
                                <th
                                    class="py-2 text-right font-medium"
                                    :class="headerClass('difficulty')"
                                    @click="onSort?.('difficulty')"
                                >
                                    <span
                                        class="inline-flex items-center justify-end gap-0.5"
                                    >
                                        Difficulty
                                        <ArrowUp
                                            v-if="
                                                sortDirection('difficulty') ===
                                                'asc'
                                            "
                                            class="size-3"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortDirection('difficulty') ===
                                                'desc'
                                            "
                                            class="size-3"
                                        />
                                    </span>
                                </th>
                                <th class="py-2 text-right font-medium">
                                    Trend
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
                                        <Skeleton class="h-4 w-full sm:w-48" />
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
                                    :key="row.keyword"
                                    class="border-b last:border-0"
                                >
                                    <td class="max-w-xs py-3 pr-4">
                                        <button
                                            type="button"
                                            class="truncate text-left font-medium text-primary hover:underline"
                                            :title="`Inspect ${row.keyword}`"
                                            @click="
                                                emit(
                                                    'select-keyword',
                                                    row.keyword,
                                                )
                                            "
                                        >
                                            {{ row.keyword }}
                                        </button>
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{ row.position ?? '—' }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.volume !== null
                                                ? formatCompact(row.volume)
                                                : '—'
                                        }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.traffic !== null
                                                ? formatNumber(row.traffic)
                                                : '—'
                                        }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.traffic_percent !== null
                                                ? `${row.traffic_percent.toFixed(2)}%`
                                                : '—'
                                        }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.cpc !== null
                                                ? `$${row.cpc.toFixed(2)}`
                                                : '—'
                                        }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{
                                            row.difficulty !== null
                                                ? row.difficulty
                                                : '—'
                                        }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <svg
                                            v-if="
                                                sparklinePoints(row.trends) !==
                                                null
                                            "
                                            width="72"
                                            height="20"
                                            viewBox="0 0 72 20"
                                            class="ml-auto inline-block"
                                        >
                                            <polyline
                                                :points="
                                                    sparklinePoints(
                                                        row.trends,
                                                    ) ?? ''
                                                "
                                                fill="none"
                                                stroke="#2563eb"
                                                stroke-width="1.5"
                                                stroke-linejoin="round"
                                                stroke-linecap="round"
                                            />
                                        </svg>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr>
                                    <td
                                        colspan="8"
                                        class="py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No organic keywords found for this
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
