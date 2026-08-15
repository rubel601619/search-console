<script setup lang="ts">
import { Link2 } from '@lucide/vue';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { formatNumber } from '@/lib/format';
import type { BacklinkOverview } from '@/types/semrush';

const props = defineProps<{
    data: BacklinkOverview | null;
    loading?: boolean;
}>();

type Stat = {
    key: keyof BacklinkOverview;
    label: string;
    value: string | null;
};

const mainStats: Stat[] = [
    { key: 'authority_score', label: 'Authority score', value: null },
    { key: 'total_backlinks', label: 'Total backlinks', value: null },
    { key: 'referring_domains', label: 'Referring domains', value: null },
    { key: 'referring_urls', label: 'Referring URLs', value: null },
    { key: 'referring_ips', label: 'Referring IPs', value: null },
    { key: 'follow_links', label: 'Follow links', value: null },
    { key: 'nofollow_links', label: 'No-follow links', value: null },
    { key: 'text_links', label: 'Text links', value: null },
];

const detailStats: Stat[] = [
    { key: 'referring_class_c_ips', label: 'Class C IPs', value: null },
    { key: 'sponsored_links', label: 'Sponsored links', value: null },
    { key: 'ugc_links', label: 'UGC links', value: null },
    { key: 'form_links', label: 'Form links', value: null },
    { key: 'frame_links', label: 'Frame links', value: null },
    { key: 'image_links', label: 'Image links', value: null },
];

function statValue(stat: Stat): string {
    const value = props.data?.[stat.key];

    return typeof value === 'number' ? formatNumber(value) : '—';
}

const resolvedMainStats = computed(() =>
    mainStats.map((stat) => ({ ...stat, value: statValue(stat) })),
);

const resolvedDetailStats = computed(() =>
    detailStats.map((stat) => ({ ...stat, value: statValue(stat) })),
);

const isLoading = computed(() => props.loading && props.data === null);
</script>

<template>
    <Card class="rounded-xl">
        <CardHeader
            class="flex flex-row items-center justify-between space-y-0"
        >
            <CardTitle class="flex items-center gap-2 text-base font-semibold">
                <Link2 class="size-4 text-muted-foreground" />
                Backlink overview
                <Spinner
                    v-if="loading && data !== null"
                    class="size-4 text-muted-foreground"
                />
            </CardTitle>
            <span v-if="data !== null" class="text-xs text-muted-foreground">
                {{ data.domain }}
            </span>
        </CardHeader>
        <CardContent>
            <div
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                :class="loading && data !== null ? 'opacity-60' : ''"
            >
                <div
                    v-for="stat in resolvedMainStats"
                    :key="stat.key"
                    class="rounded-lg border p-3"
                >
                    <Skeleton v-if="isLoading" class="h-6 w-20" />
                    <template v-else>
                        <p class="text-xs text-muted-foreground">
                            {{ stat.label }}
                        </p>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ stat.value }}
                        </p>
                    </template>
                </div>
            </div>
            <div
                class="mt-4 flex flex-wrap gap-x-6 gap-y-1 border-t pt-3 text-xs text-muted-foreground"
            >
                <span v-for="stat in resolvedDetailStats" :key="stat.key">
                    {{ stat.label }}:
                    <span class="font-medium text-foreground">{{
                        stat.value
                    }}</span>
                </span>
            </div>
        </CardContent>
    </Card>
</template>
