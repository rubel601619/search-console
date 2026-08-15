import axios from 'axios';
import {
    apiBalance,
    backlinks,
    competitors,
    domainOverview as domainOverviewRoute,
    keywordOverview as keywordOverviewRoute,
    organicKeywords as organicKeywordsRoute,
} from '@/actions/App/Http/Controllers/SemrushController';
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
import type { QueryParams } from '@/wayfinder';

type ApiEnvelope<T> = {
    data: T;
};

type SemrushErrorEnvelope = {
    message: string;
    meta?: {
        code?: string;
        retryable?: boolean;
    };
};

type RequestOptions = {
    limit?: number;
    offset?: number;
    sort?: string;
};

function toQueryParams(
    filters: SemrushFilters,
    options: RequestOptions = {},
): QueryParams {
    const params: QueryParams = {
        domain: filters.domain,
        database: filters.database,
        device: filters.device,
    };

    if (options.limit !== undefined) {
        params.limit = options.limit;
    }

    if (options.offset !== undefined) {
        params.offset = options.offset;
    }

    if (options.sort !== undefined) {
        params.sort = options.sort;
    }

    return params;
}

async function get<T>(url: string): Promise<T> {
    const { data } = await axios.get<ApiEnvelope<T>>(url);

    return data.data;
}

export async function fetchApiBalance(): Promise<ApiBalance> {
    return get<ApiBalance>(apiBalance.url());
}

export async function fetchDomainOverview(
    filters: SemrushFilters,
): Promise<DomainOverview> {
    return get<DomainOverview>(
        domainOverviewRoute.url({ query: toQueryParams(filters) }),
    );
}

export async function fetchOrganicKeywords(
    filters: SemrushFilters,
    options: RequestOptions = {},
): Promise<PaginatedResult<OrganicKeywordRow>> {
    return get<PaginatedResult<OrganicKeywordRow>>(
        organicKeywordsRoute.url({ query: toQueryParams(filters, options) }),
    );
}

export async function fetchCompetitors(
    filters: SemrushFilters,
    options: RequestOptions = {},
): Promise<PaginatedResult<CompetitorRow>> {
    return get<PaginatedResult<CompetitorRow>>(
        competitors.url({ query: toQueryParams(filters, options) }),
    );
}

export async function fetchBacklinks(
    domain: string,
): Promise<BacklinkOverview> {
    return get<BacklinkOverview>(backlinks.url({ query: { domain } }));
}

export async function fetchKeywordOverview(
    keyword: string,
    database: string,
    device: string,
): Promise<KeywordOverview> {
    return get<KeywordOverview>(
        keywordOverviewRoute.url({ query: { keyword, database, device } }),
    );
}

export function toSemrushError(error: unknown): SemrushError {
    if (axios.isAxiosError(error)) {
        const payload = error.response?.data as
            SemrushErrorEnvelope | undefined;

        return {
            message:
                payload?.message ??
                'Something went wrong while fetching Semrush data.',
            code: payload?.meta?.code ?? 'semrush_error',
            retryable: payload?.meta?.retryable ?? false,
        };
    }

    return {
        message: 'Something went wrong while fetching Semrush data.',
        code: 'semrush_error',
        retryable: false,
    };
}
