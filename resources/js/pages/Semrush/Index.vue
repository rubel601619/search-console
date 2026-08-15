<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PlugZap, ScanSearch } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import ApiUsageCard from '@/components/Semrush/ApiUsageCard.vue';
import BacklinkOverview from '@/components/Semrush/BacklinkOverview.vue';
import CompetitorsTable from '@/components/Semrush/CompetitorsTable.vue';
import DatabaseSelector from '@/components/Semrush/DatabaseSelector.vue';
import DomainSearch from '@/components/Semrush/DomainSearch.vue';
import EmptyState from '@/components/Semrush/EmptyState.vue';
import ErrorState from '@/components/Semrush/ErrorState.vue';
import KeywordOverview from '@/components/Semrush/KeywordOverview.vue';
import KeywordPositionChart from '@/components/Semrush/KeywordPositionChart.vue';
import MetricCards from '@/components/Semrush/MetricCards.vue';
import OrganicKeywordsTable from '@/components/Semrush/OrganicKeywordsTable.vue';
import { Card, CardContent } from '@/components/ui/card';
import { useSemrushData } from '@/composables/useSemrushData';
import { semrush } from '@/routes';

const props = defineProps<{
    configured: boolean;
    databases: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Semrush SEO',
                href: semrush(),
            },
        ],
    },
});

const {
    domain,
    database,
    device,
    hasDomain,
    overview,
    keywords,
    competitors,
    backlinks,
    keywordOverview,
    balance,
    activeKeyword,
    keywordSort,
    competitorSort,
    keywordsPage,
    competitorsPage,
    loading,
    errors,
    isAnyLoading,
    loadBalance,
    analyze,
    changeDatabase,
    changeDevice,
    changeKeywordsSort,
    changeCompetitorsSort,
    changeKeywordsPage,
    changeCompetitorsPage,
    selectKeyword,
    retry,
} = useSemrushData();

const keywordSearch = ref('');

onMounted(() => {
    if (props.configured) {
        void loadBalance();
    }
});
</script>

<template>
    <Head title="Semrush SEO" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="space-y-1">
                <h1 class="text-lg font-semibold">Semrush SEO</h1>
                <p class="text-sm text-muted-foreground">
                    Analyze organic performance, competitors, and backlinks.
                </p>
            </div>
            <div class="w-full lg:w-64">
                <ApiUsageCard :balance="balance" :loading="loading.balance" />
            </div>
        </div>

        <EmptyState
            v-if="!configured"
            title="Semrush API is not configured"
            description="Add your SEMRUSH_API_KEY to the local environment configuration to start using this dashboard."
            :icon="PlugZap"
        />

        <template v-else>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <DomainSearch
                    :domain="domain"
                    :disabled="isAnyLoading"
                    @analyze="analyze"
                />
                <DatabaseSelector
                    :databases="databases"
                    :database="database"
                    :device="device"
                    :disabled="isAnyLoading"
                    @update:database="changeDatabase"
                    @update:device="changeDevice"
                />
            </div>

            <EmptyState
                v-if="!hasDomain"
                title="Enter a domain to begin"
                description="Search for a domain to see its organic keywords, competitors, and backlink profile."
                :icon="ScanSearch"
            />

            <template v-else>
                <Card v-if="errors.overview !== null && overview === null">
                    <CardContent>
                        <ErrorState
                            :error="errors.overview"
                            :on-retry="() => retry('overview')"
                        />
                    </CardContent>
                </Card>
                <template v-else>
                    <MetricCards
                        :overview="overview"
                        :loading="loading.overview"
                    />
                    <KeywordPositionChart
                        :buckets="overview?.position_distribution ?? null"
                        :loading="loading.overview"
                    />
                </template>

                <div class="grid gap-6 xl:grid-cols-2">
                    <OrganicKeywordsTable
                        v-model:search="keywordSearch"
                        :rows="keywords"
                        :loading="loading.keywords"
                        :error="errors.keywords"
                        :on-retry="() => retry('keywords')"
                        :page="keywordsPage"
                        :has-more="keywords?.has_more"
                        :on-previous-page="
                            () => changeKeywordsPage(keywordsPage - 1)
                        "
                        :on-next-page="
                            () => changeKeywordsPage(keywordsPage + 1)
                        "
                        :disabled="loading.keywords"
                        :sort="keywordSort"
                        :on-sort="changeKeywordsSort"
                        @select-keyword="selectKeyword"
                    />
                    <CompetitorsTable
                        :rows="competitors"
                        :loading="loading.competitors"
                        :error="errors.competitors"
                        :on-retry="() => retry('competitors')"
                        :page="competitorsPage"
                        :has-more="competitors?.has_more"
                        :on-previous-page="
                            () => changeCompetitorsPage(competitorsPage - 1)
                        "
                        :on-next-page="
                            () => changeCompetitorsPage(competitorsPage + 1)
                        "
                        :disabled="loading.competitors"
                        :sort="competitorSort"
                        :on-sort="changeCompetitorsSort"
                    />
                </div>

                <BacklinkOverview
                    :data="backlinks"
                    :loading="loading.backlinks"
                />

                <KeywordOverview
                    v-if="
                        activeKeyword !== '' ||
                        keywordOverview !== null ||
                        errors['keyword-overview'] !== null
                    "
                    :data="keywordOverview"
                    :loading="loading['keyword-overview']"
                    :error="errors['keyword-overview']"
                    :on-retry="() => retry('keyword-overview')"
                />
            </template>
        </template>
    </div>
</template>
