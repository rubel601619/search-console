<script setup lang="ts">
import { ListFilter, RotateCcw } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { countryLabel } from '@/lib/format';
import type { SearchConsoleFilters } from '@/types/search-console';

const props = defineProps<{
    country: string;
    device: string;
    pageUrl: string;
    query: string;
    activeCount: number;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    apply: [filters: Pick<SearchConsoleFilters, 'country' | 'device' | 'pageUrl' | 'query'>];
    reset: [];
}>();

const COUNTRY_CODES = [
    'USA', 'GBR', 'DEU', 'FRA', 'IND', 'JPN', 'BRA', 'CAN', 'AUS', 'ITA',
    'NLD', 'ESP', 'MEX', 'KOR', 'TUR', 'POL', 'SWE', 'CHE', 'ARG', 'IDN',
];

const ALL_FILTER_VALUE = '__all__';

const draft = reactive({
    country: props.country,
    device: props.device,
    pageUrl: props.pageUrl,
    query: props.query,
});

const selectedCountry = computed({
    get: () => draft.country || ALL_FILTER_VALUE,
    set: (value: string) => {
        draft.country = value === ALL_FILTER_VALUE ? '' : value;
    },
});

const selectedDevice = computed({
    get: () => draft.device || ALL_FILTER_VALUE,
    set: (value: string) => {
        draft.device = value === ALL_FILTER_VALUE ? '' : value;
    },
});

watch(
    () => [props.country, props.device, props.pageUrl, props.query] as const,
    ([country, device, pageUrl, query]) => {
        draft.country = country;
        draft.device = device;
        draft.pageUrl = pageUrl;
        draft.query = query;
    },
);

function apply(): void {
    emit('apply', { ...draft });
}

function reset(): void {
    emit('reset');
}
</script>

<template>
    <Card class="rounded-xl">
        <CardHeader class="flex flex-row items-center justify-between gap-2 space-y-0 py-4">
            <CardTitle class="flex items-center gap-2 text-sm font-medium">
                <ListFilter class="size-4 text-muted-foreground" />
                Filters
                <span
                    v-if="activeCount > 0"
                    class="rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground"
                >
                    {{ activeCount }}
                </span>
            </CardTitle>
            <Button
                variant="ghost"
                size="sm"
                :disabled="activeCount === 0 || disabled"
                @click="reset"
            >
                <RotateCcw />
                Reset
            </Button>
        </CardHeader>
        <CardContent class="grid gap-4 pb-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="space-y-1.5">
                <Label for="filter-country" class="text-xs text-muted-foreground">Country</Label>
                <Select v-model="selectedCountry" :disabled="disabled">
                    <SelectTrigger id="filter-country" class="w-full">
                        <SelectValue placeholder="All countries" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL_FILTER_VALUE">All countries</SelectItem>
                        <SelectItem
                            v-for="code in COUNTRY_CODES"
                            :key="code"
                            :value="code"
                        >
                            {{ countryLabel(code) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-1.5">
                <Label for="filter-device" class="text-xs text-muted-foreground">Device</Label>
                <Select v-model="selectedDevice" :disabled="disabled">
                    <SelectTrigger id="filter-device" class="w-full">
                        <SelectValue placeholder="All devices" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL_FILTER_VALUE">All devices</SelectItem>
                        <SelectItem value="DESKTOP">Desktop</SelectItem>
                        <SelectItem value="MOBILE">Mobile</SelectItem>
                        <SelectItem value="TABLET">Tablet</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-1.5">
                <Label for="filter-query" class="text-xs text-muted-foreground">Query</Label>
                <Input
                    id="filter-query"
                    v-model="draft.query"
                    type="search"
                    placeholder="Search term"
                    :disabled="disabled"
                />
            </div>
            <div class="space-y-1.5">
                <Label for="filter-page" class="text-xs text-muted-foreground">Page</Label>
                <Input
                    id="filter-page"
                    v-model="draft.pageUrl"
                    type="search"
                    placeholder="Page URL"
                    :disabled="disabled"
                />
            </div>
            <div class="md:col-span-2 xl:col-span-4">
                <Button
                    size="sm"
                    :disabled="disabled"
                    @click="apply"
                >
                    <ListFilter />
                    Apply filters
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
