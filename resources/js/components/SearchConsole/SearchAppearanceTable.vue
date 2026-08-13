<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import MetricsTable from '@/components/SearchConsole/MetricsTable.vue';
import { appearanceLabel } from '@/lib/format';
import type { MetricsRow, SearchConsoleError } from '@/types/search-console';

withDefaults(
    defineProps<{
        rows: MetricsRow[] | null;
        loading?: boolean;
        error?: SearchConsoleError | null;
        onRetry?: () => void;
        disabled?: boolean;
    }>(),
    {
        loading: false,
        error: null,
        disabled: false,
    },
);
</script>

<template>
    <MetricsTable
        title="Search Appearance"
        :icon="Sparkles"
        :rows="rows"
        :loading="loading"
        :error="error"
        :on-retry="onRetry"
        empty-message="No search appearance data for this period."
        :format-first-column="appearanceLabel"
        first-column-label="Feature"
        show-bar
        :disabled="disabled"
    />
</template>
