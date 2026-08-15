import { computed, reactive, ref } from 'vue';
import {
    fetchApiBalance,
    fetchBacklinks,
    fetchCompetitors,
    fetchDomainOverview,
    fetchKeywordOverview,
    fetchOrganicKeywords,
    toSemrushError,
} from '@/lib/semrushApi';
import type {
    ApiBalance,
    BacklinkOverview,
    CompetitorRow,
    DomainOverview,
    KeywordOverview,
    OrganicKeywordRow,
    PaginatedResult,
    SemrushError,
    SemrushFilters,
} from '@/types/semrush';

export type SemrushSection =
    | 'overview'
    | 'keywords'
    | 'competitors'
    | 'backlinks'
    | 'keyword-overview'
    | 'balance';

const DEFAULT_LIMIT = 20;

export function useSemrushData() {
    const domain = ref('');
    const database = ref('us');
    const device = ref<'desktop' | 'mobile'>('desktop');

    const overview = ref<DomainOverview | null>(null);
    const keywords = ref<PaginatedResult<OrganicKeywordRow> | null>(null);
    const competitors = ref<PaginatedResult<CompetitorRow> | null>(null);
    const backlinks = ref<BacklinkOverview | null>(null);
    const keywordOverview = ref<KeywordOverview | null>(null);
    const balance = ref<ApiBalance | null>(null);

    const activeKeyword = ref('');
    const keywordSort = ref('');
    const competitorSort = ref('');
    const keywordsPage = ref(1);
    const competitorsPage = ref(1);

    const loading = reactive<Record<SemrushSection, boolean>>({
        overview: false,
        keywords: false,
        competitors: false,
        backlinks: false,
        'keyword-overview': false,
        balance: false,
    });

    const errors = reactive<Record<SemrushSection, SemrushError | null>>({
        overview: null,
        keywords: null,
        competitors: null,
        backlinks: null,
        'keyword-overview': null,
        balance: null,
    });

    const hasDomain = computed(() => domain.value !== '');
    const filters = computed<SemrushFilters>(() => ({
        domain: domain.value,
        database: database.value,
        device: device.value,
    }));
    const isAnyLoading = computed(() =>
        Object.entries(loading)
            .filter(([key]) => key !== 'balance' && key !== 'keyword-overview')
            .some(([, value]) => value),
    );

    async function loadBalance(): Promise<void> {
        loading.balance = true;
        errors.balance = null;

        try {
            balance.value = await fetchApiBalance();
        } catch (error) {
            errors.balance = toSemrushError(error);
        } finally {
            loading.balance = false;
        }
    }

    async function loadSection(section: SemrushSection): Promise<void> {
        if (section !== 'balance' && !hasDomain.value) {
            return;
        }

        loading[section] = true;
        errors[section] = null;

        try {
            switch (section) {
                case 'overview':
                    overview.value = await fetchDomainOverview(filters.value);
                    break;
                case 'keywords':
                    keywords.value = await fetchOrganicKeywords(filters.value, {
                        limit: DEFAULT_LIMIT,
                        offset: (keywordsPage.value - 1) * DEFAULT_LIMIT,
                        sort:
                            keywordSort.value !== ''
                                ? keywordSort.value
                                : undefined,
                    });
                    break;
                case 'competitors':
                    competitors.value = await fetchCompetitors(filters.value, {
                        limit: DEFAULT_LIMIT,
                        offset: (competitorsPage.value - 1) * DEFAULT_LIMIT,
                        sort:
                            competitorSort.value !== ''
                                ? competitorSort.value
                                : undefined,
                    });
                    break;
                case 'backlinks':
                    backlinks.value = await fetchBacklinks(domain.value);
                    break;
                case 'keyword-overview':
                    if (activeKeyword.value !== '') {
                        keywordOverview.value = await fetchKeywordOverview(
                            activeKeyword.value,
                            database.value,
                            device.value,
                        );
                    }

                    break;
                case 'balance':
                    await loadBalance();
                    break;
            }
        } catch (error) {
            errors[section] = toSemrushError(error);
        } finally {
            loading[section] = false;
        }
    }

    function resetPageState(): void {
        keywordsPage.value = 1;
        competitorsPage.value = 1;
        keywordSort.value = '';
        competitorSort.value = '';
        activeKeyword.value = '';
        keywordOverview.value = null;
        overview.value = null;
        keywords.value = null;
        competitors.value = null;
        backlinks.value = null;
    }

    function loadAll(): void {
        void Promise.all([
            loadSection('overview'),
            loadSection('keywords'),
            loadSection('competitors'),
            loadSection('backlinks'),
        ]);
    }

    function analyze(nextDomain: string): void {
        const normalized = nextDomain.trim();

        if (normalized === '' || normalized === domain.value) {
            return;
        }

        domain.value = normalized;
        resetPageState();
        loadAll();
    }

    function changeDatabase(next: string): void {
        if (next === database.value) {
            return;
        }

        database.value = next;
        resetPageState();
        loadAll();
    }

    function changeDevice(next: 'desktop' | 'mobile'): void {
        if (next === device.value) {
            return;
        }

        device.value = next;
        resetPageState();
        loadAll();
    }

    function changeKeywordsSort(sort: string): void {
        keywordSort.value = keywordSort.value === sort ? `-${sort}` : sort;
        keywordsPage.value = 1;
        void loadSection('keywords');
    }

    function changeCompetitorsSort(sort: string): void {
        competitorSort.value =
            competitorSort.value === sort ? `-${sort}` : sort;
        competitorsPage.value = 1;
        void loadSection('competitors');
    }

    function changeKeywordsPage(page: number): void {
        keywordsPage.value = page;
        void loadSection('keywords');
    }

    function changeCompetitorsPage(page: number): void {
        competitorsPage.value = page;
        void loadSection('competitors');
    }

    function selectKeyword(keyword: string): void {
        activeKeyword.value = keyword;
        void loadSection('keyword-overview');
    }

    function retry(section: SemrushSection): void {
        if (section === 'keyword-overview' && activeKeyword.value === '') {
            return;
        }

        void loadSection(section);
    }

    return {
        domain,
        database,
        device,
        hasDomain,
        filters,
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
    };
}
