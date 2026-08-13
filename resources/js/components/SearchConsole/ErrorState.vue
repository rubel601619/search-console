<script setup lang="ts">
import { CircleAlert, RefreshCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { SearchConsoleError } from '@/types/search-console';

withDefaults(
    defineProps<{
        error: SearchConsoleError;
        onRetry?: () => void;
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 px-6 text-center"
        :class="compact ? 'py-8' : 'py-16'"
    >
        <div class="flex size-10 items-center justify-center rounded-full bg-destructive/10">
            <CircleAlert class="size-5 text-destructive" />
        </div>
        <div class="space-y-1">
            <p class="text-sm font-medium">{{ error.message }}</p>
            <p v-if="error.retryable" class="text-xs text-muted-foreground">
                This can happen due to Google rate limits. Please try again in a moment.
            </p>
        </div>
        <Button v-if="onRetry" variant="outline" size="sm" @click="onRetry">
            <RefreshCw />
            Retry
        </Button>
    </div>
</template>
