<script setup lang="ts">
import { FileText } from '@lucide/vue';
import MetricsTable from '@/components/SearchConsole/MetricsTable.vue';
import type { MetricsRow, SearchConsoleError } from '@/types/search-console';

withDefaults(
    defineProps<{
        rows: MetricsRow[] | null;
        loading?: boolean;
        error?: SearchConsoleError | null;
        onRetry?: () => void;
        page?: number;
        hasMore?: boolean;
        onPreviousPage?: () => void;
        onNextPage?: () => void;
        disabled?: boolean;
    }>(),
    {
        loading: false,
        error: null,
        disabled: false,
    },
);

const search = defineModel<string>('search', { default: '' });
</script>

<template>
    <MetricsTable
        v-model:search="search"
        title="Top Pages"
        :icon="FileText"
        :rows="rows"
        :loading="loading"
        :error="error"
        :on-retry="onRetry"
        empty-message="No pages found for this period."
        searchable
        first-column-label="Page"
        :page="page"
        :has-more="hasMore"
        :on-previous-page="onPreviousPage"
        :on-next-page="onNextPage"
        :disabled="disabled"
    />
</template>
