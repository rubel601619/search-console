<script setup lang="ts">
import { Coins } from '@lucide/vue';
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatNumber } from '@/lib/format';
import type { ApiBalance } from '@/types/semrush';

const props = defineProps<{
    balance: ApiBalance | null;
    loading?: boolean;
}>();

const isLoading = computed(() => props.loading && props.balance === null);
</script>

<template>
    <Card class="rounded-xl">
        <CardContent class="flex items-center gap-3 p-3 sm:p-4">
            <div
                class="flex size-9 items-center justify-center rounded-full bg-primary/10"
            >
                <Coins class="size-4 text-primary" />
            </div>
            <div class="space-y-0.5">
                <p class="text-xs text-muted-foreground">API units left</p>
                <Skeleton v-if="isLoading" class="h-5 w-16" />
                <p
                    v-else
                    class="text-base leading-none font-semibold tabular-nums"
                >
                    {{
                        balance?.units_left !== null &&
                        balance?.units_left !== undefined
                            ? formatNumber(balance.units_left)
                            : '—'
                    }}
                </p>
                <p
                    v-if="
                        balance?.units_used !== null &&
                        balance?.units_used !== undefined
                    "
                    class="text-xs text-muted-foreground"
                >
                    {{ formatNumber(balance.units_used) }} used
                </p>
            </div>
        </CardContent>
    </Card>
</template>
