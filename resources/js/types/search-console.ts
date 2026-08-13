export type SearchConsoleProperty = {
    url: string;
    permission_level: string;
};

export type MetricsRow = {
    key: string;
    clicks: number;
    impressions: number;
    ctr: number;
    position: number;
};

export type MetricsTotals = {
    clicks: number;
    impressions: number;
    ctr: number;
    position: number;
};

export type MetricsComparison = {
    clicks: number | null;
    impressions: number | null;
    ctr: number | null;
    position: number | null;
};

export type AnalyticsOverview = {
    totals: MetricsTotals;
    previous: MetricsTotals | null;
    comparison: MetricsComparison | null;
    daily: MetricsRow[];
};

export type DimensionResult = {
    rows: MetricsRow[];
    has_more: boolean;
    page: number;
};

export type DateRange = {
    startDate: string;
    endDate: string;
};

export type SearchConsoleFilters = {
    property: string;
    startDate: string;
    endDate: string;
    country: string;
    device: string;
    pageUrl: string;
    query: string;
};

export type SearchConsoleError = {
    message: string;
    code: string;
    retryable: boolean;
};
