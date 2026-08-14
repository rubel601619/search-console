<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PlugZap, Satellite } from '@lucide/vue';
import { computed, onMounted } from 'vue';
import { connect } from '@/actions/App/Http/Controllers/GoogleAuthController';
import CountryTable from '@/components/SearchConsole/CountryTable.vue';
import DateRangePicker from '@/components/SearchConsole/DateRangePicker.vue';
import DeviceChart from '@/components/SearchConsole/DeviceChart.vue';
import EmptyState from '@/components/SearchConsole/EmptyState.vue';
import ErrorState from '@/components/SearchConsole/ErrorState.vue';
import FilterPanel from '@/components/SearchConsole/FilterPanel.vue';
import MetricCards from '@/components/SearchConsole/MetricCards.vue';
import PageTable from '@/components/SearchConsole/PageTable.vue';
import PerformanceChart from '@/components/SearchConsole/PerformanceChart.vue';
import PropertySelector from '@/components/SearchConsole/PropertySelector.vue';
import QueryTable from '@/components/SearchConsole/QueryTable.vue';
import SearchAppearanceTable from '@/components/SearchConsole/SearchAppearanceTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useSearchConsoleData } from '@/composables/useSearchConsoleData';
import { searchConsole } from '@/routes';
import type { DateRange } from '@/types/search-console';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Search Console',
                href: searchConsole(),
            },
        ],
    },
});

const {
    properties,
    propertiesLoading,
    propertiesError,
    loadProperties,
    filters,
    activeFilterCount,
    analytics,
    queries,
    pages,
    countries,
    devices,
    searchAppearance,
    queriesPage,
    pagesPage,
    loading,
    errors,
    setProperty,
    setDateRange,
    applyFilters,
    clearFilters,
    changeQueriesPage,
    changePagesPage,
    retry,
} = useSearchConsoleData();

onMounted(() => {
    void loadProperties();
});

const needsConnection = computed(
    () =>
        !propertiesLoading.value
        && propertiesError.value?.code === 'not_connected',
);

const hasProperties = computed(() => properties.value.length > 0);

const isAnyLoading = computed(() =>
    Object.values(loading).some((value) => value),
);

const dateRange = computed<DateRange>({
    get: () => ({
        startDate: filters.value.startDate,
        endDate: filters.value.endDate,
    }),
    set: (range) => setDateRange(range.startDate, range.endDate),
});
</script>

<template>
    <Head title="Search Console" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-1">
                <h1 class="text-lg font-semibold">Search Console</h1>
                <p class="text-sm text-muted-foreground">
                    Monitor your site's organic search performance.
                </p>
            </div>
            <PropertySelector
                :properties="properties"
                :model-value="filters.property"
                :loading="propertiesLoading"
                @update:model-value="setProperty"
            />
        </div>

        <Skeleton v-if="propertiesLoading" class="h-32 w-full rounded-xl" />

        <EmptyState
            v-else-if="needsConnection"
            title="Connect your Google account"
            description="Connect a Google account with access to Google Search Console to start monitoring your organic performance."
            :icon="PlugZap"
        >
            <Button as-child>
                <a :href="connect.url()">Connect Google account</a>
            </Button>
        </EmptyState>

        <Card v-else-if="propertiesError !== null">
            <CardContent>
                <ErrorState
                    :error="propertiesError"
                    :on-retry="loadProperties"
                />
            </CardContent>
        </Card>

        <EmptyState
            v-else-if="!hasProperties"
            title="No Search Console properties found"
            description="Add your site to Google Search Console and verify ownership, then reload this page."
            :icon="Satellite"
        >
            <Button variant="outline" @click="loadProperties">
                Reload properties
            </Button>
        </EmptyState>

        <template v-else>
            <DateRangePicker v-model="dateRange" :disabled="isAnyLoading" />

            <FilterPanel
                :country="filters.country"
                :device="filters.device"
                :page-url="filters.pageUrl"
                :query="filters.query"
                :active-count="activeFilterCount"
                :disabled="isAnyLoading"
                @apply="applyFilters"
                @reset="clearFilters"
            />

            <Card v-if="errors.analytics !== null && analytics === null">
                <CardContent>
                    <ErrorState
                        :error="errors.analytics"
                        :on-retry="() => retry('analytics')"
                    />
                </CardContent>
            </Card>
            <template v-else>
                <MetricCards
                    :totals="analytics?.totals ?? null"
                    :comparison="analytics?.comparison ?? null"
                    :loading="loading.analytics"
                />
                <PerformanceChart
                    :rows="analytics?.daily ?? null"
                    :loading="loading.analytics"
                />
            </template>

            <div class="grid gap-6 xl:grid-cols-2">
                <QueryTable
                    :rows="queries?.rows ?? null"
                    :loading="loading.queries"
                    :error="errors.queries"
                    :on-retry="() => retry('queries')"
                    :page="queriesPage"
                    :has-more="queries?.has_more"
                    :on-previous-page="() => changeQueriesPage(queriesPage - 1)"
                    :on-next-page="() => changeQueriesPage(queriesPage + 1)"
                    :disabled="loading.queries"
                />
                <PageTable
                    :rows="pages?.rows ?? null"
                    :loading="loading.pages"
                    :error="errors.pages"
                    :on-retry="() => retry('pages')"
                    :page="pagesPage"
                    :has-more="pages?.has_more"
                    :on-previous-page="() => changePagesPage(pagesPage - 1)"
                    :on-next-page="() => changePagesPage(pagesPage + 1)"
                    :disabled="loading.pages"
                />
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <CountryTable
                    :rows="countries?.rows ?? null"
                    :loading="loading.countries"
                    :error="errors.countries"
                    :on-retry="() => retry('countries')"
                />
                <DeviceChart
                    :rows="devices?.rows ?? null"
                    :loading="loading.devices"
                    :error="errors.devices"
                    :on-retry="() => retry('devices')"
                />
            </div>

            <SearchAppearanceTable
                :rows="searchAppearance?.rows ?? null"
                :loading="loading['search-appearance']"
                :error="errors['search-appearance']"
                :on-retry="() => retry('search-appearance')"
            />
        </template>
    </div>
</template>
