<script setup lang="ts">
import { Globe } from '@lucide/vue';
import MetricsTable from '@/components/SearchConsole/MetricsTable.vue';
import { countryLabel } from '@/lib/format';
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
        title="Countries"
        :icon="Globe"
        :rows="rows"
        :loading="loading"
        :error="error"
        :on-retry="onRetry"
        empty-message="No country data for this period."
        :format-first-column="countryLabel"
        first-column-label="Country"
        show-bar
        :disabled="disabled"
    />
</template>
