import axios from 'axios';
import {
    analytics,
    countries,
    devices,
    pages,
    properties,
    queries,
    searchAppearance,
} from '@/actions/App/Http/Controllers/SearchConsoleController';
import type {
    AnalyticsOverview,
    DimensionResult,
    SearchConsoleError,
    SearchConsoleFilters,
    SearchConsoleProperty,
} from '@/types/search-console';
import type { QueryParams } from '@/wayfinder';

type ApiEnvelope<T> = {
    data: T;
};

type RequestOptions = {
    perPage?: number;
    page?: number;
};

function toQueryParams(filters: SearchConsoleFilters, options: RequestOptions = {}): QueryParams {
    const params: QueryParams = {
        property: filters.property,
        start_date: filters.startDate,
        end_date: filters.endDate,
    };

    if (filters.country !== '') {
        params.country = filters.country;
    }

    if (filters.device !== '') {
        params.device = filters.device;
    }

    if (filters.pageUrl !== '') {
        params.page_url = filters.pageUrl;
    }

    if (filters.query !== '') {
        params.query = filters.query;
    }

    if (options.perPage !== undefined) {
        params.per_page = options.perPage;
    }

    if (options.page !== undefined) {
        params.page = options.page;
    }

    return params;
}

async function get<T>(url: string): Promise<T> {
    const { data } = await axios.get<ApiEnvelope<T>>(url);

    return data.data;
}

export async function fetchProperties(): Promise<SearchConsoleProperty[]> {
    return get<SearchConsoleProperty[]>(properties.url());
}

export async function fetchAnalytics(filters: SearchConsoleFilters, options: RequestOptions = {}): Promise<AnalyticsOverview> {
    return get<AnalyticsOverview>(analytics.url({ query: toQueryParams(filters, options) }));
}

export async function fetchQueries(filters: SearchConsoleFilters, options: RequestOptions = {}): Promise<DimensionResult> {
    return get<DimensionResult>(queries.url({ query: toQueryParams(filters, options) }));
}

export async function fetchPages(filters: SearchConsoleFilters, options: RequestOptions = {}): Promise<DimensionResult> {
    return get<DimensionResult>(pages.url({ query: toQueryParams(filters, options) }));
}

export async function fetchCountries(filters: SearchConsoleFilters, options: RequestOptions = {}): Promise<DimensionResult> {
    return get<DimensionResult>(countries.url({ query: toQueryParams(filters, options) }));
}

export async function fetchDevices(filters: SearchConsoleFilters, options: RequestOptions = {}): Promise<DimensionResult> {
    return get<DimensionResult>(devices.url({ query: toQueryParams(filters, options) }));
}

export async function fetchSearchAppearance(filters: SearchConsoleFilters, options: RequestOptions = {}): Promise<DimensionResult> {
    return get<DimensionResult>(searchAppearance.url({ query: toQueryParams(filters, options) }));
}

export function toSearchConsoleError(error: unknown): SearchConsoleError {
    if (axios.isAxiosError(error)) {
        const payload = error.response?.data as Partial<SearchConsoleError> | undefined;

        return {
            message: payload?.message ?? 'Something went wrong while fetching Search Console data.',
            code: payload?.code ?? 'search_console_error',
            retryable: payload?.retryable ?? false,
        };
    }

    return {
        message: 'Something went wrong while fetching Search Console data.',
        code: 'search_console_error',
        retryable: false,
    };
}
