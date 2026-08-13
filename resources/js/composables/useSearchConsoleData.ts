import { computed, reactive, ref } from 'vue';
import {
    fetchAnalytics,
    fetchCountries,
    fetchDevices,
    fetchPages,
    fetchProperties,
    fetchQueries,
    fetchSearchAppearance,
    toSearchConsoleError,
} from '@/lib/searchConsoleApi';
import type {
    AnalyticsOverview,
    DimensionResult,
    SearchConsoleError,
    SearchConsoleFilters,
    SearchConsoleProperty,
} from '@/types/search-console';

export type SearchConsoleSection =
    | 'analytics'
    | 'queries'
    | 'pages'
    | 'countries'
    | 'devices'
    | 'search-appearance';

const DEFAULT_PER_PAGE = 10;
const MAX_ANALYTICS_DAYS = 1000;

function toISODate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function daysBetween(startDate: string, endDate: string): number {
    const start = new Date(`${startDate}T00:00:00`);
    const end = new Date(`${endDate}T00:00:00`);

    return Math.round((end.getTime() - start.getTime()) / 86_400_000) + 1;
}

function defaultFilters(): SearchConsoleFilters {
    const today = new Date();
    const start = new Date(today);

    start.setDate(start.getDate() - 27);

    return {
        property: '',
        startDate: toISODate(start),
        endDate: toISODate(today),
        country: '',
        device: '',
        pageUrl: '',
        query: '',
    };
}

export function useSearchConsoleData() {
    const properties = ref<SearchConsoleProperty[]>([]);
    const propertiesLoading = ref(false);
    const propertiesError = ref<SearchConsoleError | null>(null);

    const filters = ref<SearchConsoleFilters>(defaultFilters());
    const analytics = ref<AnalyticsOverview | null>(null);
    const queries = ref<DimensionResult | null>(null);
    const pages = ref<DimensionResult | null>(null);
    const countries = ref<DimensionResult | null>(null);
    const devices = ref<DimensionResult | null>(null);
    const searchAppearance = ref<DimensionResult | null>(null);

    const queriesPage = ref(1);
    const pagesPage = ref(1);

    const loading = reactive<Record<SearchConsoleSection, boolean>>({
        analytics: false,
        queries: false,
        pages: false,
        countries: false,
        devices: false,
        'search-appearance': false,
    });

    const errors = reactive<Record<SearchConsoleSection, SearchConsoleError | null>>({
        analytics: null,
        queries: null,
        pages: null,
        countries: null,
        devices: null,
        'search-appearance': null,
    });

    const connected = computed(() => filters.value.property !== '');
    const activeFilterCount = computed(
        () =>
            (['country', 'device', 'pageUrl', 'query'] as const).filter(
                (key) => filters.value[key] !== '',
            ).length,
    );

    function analyticsPerPage(): number {
        return Math.min(Math.max(daysBetween(filters.value.startDate, filters.value.endDate), 1), MAX_ANALYTICS_DAYS);
    }

    async function loadProperties(): Promise<void> {
        propertiesLoading.value = true;
        propertiesError.value = null;

        try {
            const result = await fetchProperties();

            properties.value = result;

            if (result.length > 0 && filters.value.property === '') {
                filters.value.property = result[0].url;
                await loadAll();
            }
        } catch (error) {
            propertiesError.value = toSearchConsoleError(error);
        } finally {
            propertiesLoading.value = false;
        }
    }

    async function loadSection(section: SearchConsoleSection): Promise<void> {
        if (!connected.value) {
            return;
        }

        loading[section] = true;
        errors[section] = null;

        try {
            const base = { ...filters.value };
            const perPage = section === 'analytics' ? analyticsPerPage() : DEFAULT_PER_PAGE;
            const page = section === 'queries' ? queriesPage.value : section === 'pages' ? pagesPage.value : 1;

            switch (section) {
                case 'analytics':
                    analytics.value = await fetchAnalytics(base, { perPage });
                    break;
                case 'queries':
                    queries.value = await fetchQueries(base, { perPage, page });
                    break;
                case 'pages':
                    pages.value = await fetchPages(base, { perPage, page });
                    break;
                case 'countries':
                    countries.value = await fetchCountries(base, { perPage });
                    break;
                case 'devices':
                    devices.value = await fetchDevices(base, { perPage });
                    break;
                case 'search-appearance':
                    searchAppearance.value = await fetchSearchAppearance(base, { perPage });
                    break;
            }
        } catch (error) {
            errors[section] = toSearchConsoleError(error);
        } finally {
            loading[section] = false;
        }
    }

    async function loadAll(): Promise<void> {
        await Promise.all([
            loadSection('analytics'),
            loadSection('queries'),
            loadSection('pages'),
            loadSection('countries'),
            loadSection('devices'),
            loadSection('search-appearance'),
        ]);
    }

    function resetPagination(): void {
        queriesPage.value = 1;
        pagesPage.value = 1;
    }

    function setProperty(property: string): void {
        filters.value.property = property;
        resetPagination();
        void loadAll();
    }

    function setDateRange(startDate: string, endDate: string): void {
        filters.value.startDate = startDate;
        filters.value.endDate = endDate;
        resetPagination();
        void loadAll();
    }

    function applyFilters(next: Pick<SearchConsoleFilters, 'country' | 'device' | 'pageUrl' | 'query'>): void {
        filters.value = { ...filters.value, ...next };
        resetPagination();
        void loadAll();
    }

    function clearFilters(): void {
        filters.value = { ...filters.value, country: '', device: '', pageUrl: '', query: '' };
        resetPagination();
        void loadAll();
    }

    function changeQueriesPage(page: number): void {
        queriesPage.value = page;
        void loadSection('queries');
    }

    function changePagesPage(page: number): void {
        pagesPage.value = page;
        void loadSection('pages');
    }

    function retry(section: SearchConsoleSection): void {
        void loadSection(section);
    }

    return {
        properties,
        propertiesLoading,
        propertiesError,
        loadProperties,
        filters,
        connected,
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
    };
}
