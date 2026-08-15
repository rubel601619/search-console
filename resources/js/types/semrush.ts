export type SemrushError = {
    message: string;
    code: string;
    retryable: boolean;
};

export type Device = 'desktop' | 'mobile';

export type SemrushFilters = {
    domain: string;
    database: string;
    device: Device;
};

export type PositionBucket = {
    label: string;
    value: number;
};

export type DomainOverview = {
    domain: string;
    rank: number | null;
    organic_keywords: number | null;
    organic_traffic: number | null;
    organic_cost: number | null;
    adwords_keywords: number | null;
    adwords_traffic: number | null;
    adwords_cost: number | null;
    position_distribution: PositionBucket[];
};

export type OrganicKeywordRow = {
    keyword: string;
    position: number | null;
    previous_position: number | null;
    position_difference: number | null;
    volume: number | null;
    cpc: number | null;
    url: string | null;
    traffic_percent: number | null;
    traffic_cost_percent: number | null;
    traffic: number | null;
    competition: number | null;
    results: number | null;
    trends: number[];
    difficulty: number | null;
    intent: string | null;
    serp_features_domain: string | null;
    serp_features_keyword: string | null;
    position_type: string | null;
};

export type CompetitorRow = {
    domain: string;
    relevance: number | null;
    common_keywords: number | null;
    organic_keywords: number | null;
    organic_traffic: number | null;
    organic_cost: number | null;
    adwords_keywords: number | null;
};

export type PaginatedResult<T> = {
    rows: T[];
    has_more: boolean;
    limit: number;
    offset: number;
    page: number;
};

export type BacklinkOverview = {
    domain: string;
    authority_score: number | null;
    total_backlinks: number | null;
    referring_domains: number | null;
    referring_urls: number | null;
    referring_ips: number | null;
    referring_class_c_ips: number | null;
    follow_links: number | null;
    nofollow_links: number | null;
    sponsored_links: number | null;
    ugc_links: number | null;
    text_links: number | null;
    image_links: number | null;
    form_links: number | null;
    frame_links: number | null;
};

export type KeywordOverview = {
    keyword: string;
    volume: number | null;
    cpc: number | null;
    competition: number | null;
    results: number | null;
    trends: number[];
    intent: string | null;
    difficulty: number | null;
};

export type ApiBalance = {
    units_left: number | null;
    units_used: number | null;
};

export const ORGANIC_KEYWORDS_SORT_COLUMNS = [
    'position',
    'volume',
    'traffic',
    'traffic_percent',
    'difficulty',
    'competition',
] as const;

export type OrganicKeywordSort = (typeof ORGANIC_KEYWORDS_SORT_COLUMNS)[number];

export const COMPETITOR_SORT_COLUMNS = [
    'relevance',
    'common_keywords',
    'organic_keywords',
    'organic_traffic',
] as const;

export type CompetitorSort = (typeof COMPETITOR_SORT_COLUMNS)[number];
