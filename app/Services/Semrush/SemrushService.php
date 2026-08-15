<?php

namespace App\Services\Semrush;

use App\Exceptions\SemrushException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SemrushService
{
    public const TYPE_DOMAIN_OVERVIEW = 'domain_rank';

    public const TYPE_ORGANIC_KEYWORDS = 'domain_organic';

    public const TYPE_ORGANIC_COMPETITORS = 'domain_organic_organic';

    public const TYPE_KEYWORD_OVERVIEW = 'phrase_this';

    public const TYPE_BACKLINK_OVERVIEW = 'backlinks_overview';

    /**
     * Sort values accepted by the organic keywords endpoint, keyed by the
     * `column` / `-column` convention used by the dashboard tables.
     */
    public const ORGANIC_KEYWORDS_SORTS = [
        'position' => 'po_asc',
        '-position' => 'po_desc',
        'volume' => 'nq_asc',
        '-volume' => 'nq_desc',
        'cpc' => 'cp_asc',
        '-cpc' => 'cp_desc',
        'traffic' => 'tg_asc',
        '-traffic' => 'tg_desc',
        'traffic_percent' => 'tr_asc',
        '-traffic_percent' => 'tr_desc',
        'difficulty' => 'kd_asc',
        '-difficulty' => 'kd_desc',
        'competition' => 'co_asc',
        '-competition' => 'co_desc',
    ];

    public const COMPETITOR_SORTS = [
        'common_keywords' => 'np_asc',
        '-common_keywords' => 'np_desc',
        'relevance' => 'cr_asc',
        '-relevance' => 'cr_desc',
        'organic_traffic' => 'ot_asc',
        '-organic_traffic' => 'ot_desc',
        'organic_keywords' => 'or_asc',
        '-organic_keywords' => 'or_desc',
    ];

    /**
     * Endpoint layout per report type. `escape` toggles the `export_decode` /
     * `export_escape` parameters, which the backlinks endpoint does not accept.
     */
    private const ENDPOINTS = [
        self::TYPE_DOMAIN_OVERVIEW => ['path' => '/', 'mobile' => true, 'escape' => true],
        self::TYPE_ORGANIC_KEYWORDS => ['path' => '/', 'mobile' => true, 'escape' => true],
        self::TYPE_ORGANIC_COMPETITORS => ['path' => '/', 'mobile' => true, 'escape' => true],
        self::TYPE_KEYWORD_OVERVIEW => ['path' => '/', 'mobile' => false, 'escape' => true],
        self::TYPE_BACKLINK_OVERVIEW => ['path' => '/analytics/v1/', 'mobile' => false, 'escape' => false],
    ];

    private const DOMAIN_OVERVIEW_COLUMNS = [
        'Dn', 'Rk', 'Or', 'Ot', 'Oc', 'Ad', 'At', 'Ac',
        'X0', 'X1', 'X2', 'X3', 'X4', 'X5', 'X6', 'X7', 'X8', 'X9', 'XA',
    ];

    private const ORGANIC_KEYWORDS_COLUMNS = [
        'Ph', 'Po', 'Pp', 'Pd', 'Nq', 'Cp', 'Ur', 'Tr', 'Tc', 'Co',
        'Nr', 'Td', 'Kd', 'In', 'Fp', 'Fk', 'Pt', 'Tg',
    ];

    private const COMPETITOR_COLUMNS = ['Dn', 'Cr', 'Np', 'Or', 'Ot', 'Oc', 'Ad'];

    private const KEYWORD_OVERVIEW_COLUMNS = ['Ph', 'Nq', 'Cp', 'Co', 'Nr', 'Td', 'In', 'Kd'];

    private const BACKLINK_COLUMNS = [
        'ascore', 'total', 'domains_num', 'urls_num', 'ips_num', 'ipclassc_num',
        'follows_num', 'nofollows_num', 'sponsored_num', 'ugc_num',
        'texts_num', 'images_num', 'forms_num', 'frames_num',
    ];

    private const POSITION_BUCKETS = [
        ['X0', '1-3'],
        ['X1', '4-10'],
        ['X2', '11-20'],
        ['X3', '21-30'],
        ['X4', '31-40'],
        ['X5', '41-50'],
        ['X6', '51-60'],
        ['X7', '61-70'],
        ['X8', '71-80'],
        ['X9', '81-90'],
        ['XA', '91-100'],
    ];

    /**
     * Retrieve the remaining API unit balance (free endpoint, cached).
     *
     * @return array{units_left: int|null, units_used: int|null}
     */
    public function getApiBalance(): array
    {
        return Cache::remember('semrush:balance', 300, function () {
            if (blank(config('semrush.api_key'))) {
                return ['units_left' => null, 'units_used' => null];
            }

            try {
                $response = Http::timeout((int) config('semrush.timeout'))
                    ->get(config('semrush.api_balance_url'), ['key' => config('semrush.api_key')]);
            } catch (Throwable $e) {
                $this->logFailure('api_balance', [], 'network_error', null, $e->getMessage());

                return ['units_left' => null, 'units_used' => null];
            }

            if ($response->failed()) {
                $this->logFailure('api_balance', [], "http_{$response->status()}", $response->status());

                return ['units_left' => null, 'units_used' => null];
            }

            $values = [];

            foreach (preg_split('/[;\s]+/', trim($response->body())) ?: [] as $part) {
                if (preg_match('/^[\d,]+$/', $part)) {
                    $values[] = (int) str_replace(',', '', $part);
                }
            }

            return [
                'units_left' => $values[0] ?? null,
                'units_used' => $values[1] ?? null,
            ];
        });
    }

    /**
     * Domain Overview (`domain_rank`) for a single regional database.
     *
     * @return array{
     *     domain: string,
     *     rank: int|null,
     *     organic_keywords: int|null,
     *     organic_traffic: int|null,
     *     organic_cost: float|null,
     *     adwords_keywords: int|null,
     *     adwords_traffic: int|null,
     *     adwords_cost: float|null,
     *     position_distribution: array<int, array{label: string, value: int}>
     * }
     */
    public function getDomainOverview(string $domain, string $database, ?string $device = null): array
    {
        $row = $this->request(
            self::TYPE_DOMAIN_OVERVIEW,
            [
                'domain' => $domain,
                'database' => $this->databaseCode($database, $device, self::TYPE_DOMAIN_OVERVIEW),
            ],
            self::DOMAIN_OVERVIEW_COLUMNS,
        )[0] ?? [];

        return [
            'domain' => $row['Dn'] ?? $domain,
            'rank' => $row['Rk'] ?? null,
            'organic_keywords' => $row['Or'] ?? null,
            'organic_traffic' => $row['Ot'] ?? null,
            'organic_cost' => $row['Oc'] ?? null,
            'adwords_keywords' => $row['Ad'] ?? null,
            'adwords_traffic' => $row['At'] ?? null,
            'adwords_cost' => $row['Ac'] ?? null,
            'position_distribution' => $this->positionDistribution($row),
        ];
    }

    /**
     * Organic keywords (`domain_organic`) for a domain.
     *
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     has_more: bool,
     *     limit: int,
     *     offset: int,
     *     page: int
     * }
     */
    public function getOrganicKeywords(
        string $domain,
        string $database,
        ?string $device = null,
        int $limit = 10,
        int $offset = 0,
        ?string $sort = null,
    ): array {
        $limit = $this->limit($limit);
        $offset = max(0, $offset);

        $params = [
            'domain' => $domain,
            'database' => $this->databaseCode($database, $device, self::TYPE_ORGANIC_KEYWORDS),
            'display_limit' => $offset + $limit,
            'display_offset' => $offset,
        ];

        if ($sort !== null && array_key_exists($sort, self::ORGANIC_KEYWORDS_SORTS)) {
            $params['display_sort'] = self::ORGANIC_KEYWORDS_SORTS[$sort];
        }

        $rows = $this->request(self::TYPE_ORGANIC_KEYWORDS, $params, self::ORGANIC_KEYWORDS_COLUMNS);

        return [
            'rows' => array_map(fn (array $row) => [
                'keyword' => $row['Ph'] ?? '',
                'position' => $row['Po'] ?? null,
                'previous_position' => $row['Pp'] ?? null,
                'position_difference' => $row['Pd'] ?? null,
                'volume' => $row['Nq'] ?? null,
                'cpc' => $row['Cp'] ?? null,
                'url' => $row['Ur'] ?? null,
                'traffic_percent' => $row['Tr'] ?? null,
                'traffic_cost_percent' => $row['Tc'] ?? null,
                'traffic' => $row['Tg'] ?? null,
                'competition' => $row['Co'] ?? null,
                'results' => $row['Nr'] ?? null,
                'trends' => $this->trends($row['Td'] ?? null),
                'difficulty' => $row['Kd'] ?? null,
                'intent' => $this->intentLabel($row['In'] ?? null),
                'serp_features_domain' => $row['Fp'] ?? null,
                'serp_features_keyword' => $row['Fk'] ?? null,
                'position_type' => $row['Pt'] ?? null,
            ], $rows),
            'has_more' => count($rows) >= $limit,
            'limit' => $limit,
            'offset' => $offset,
            'page' => intdiv($offset, max($limit, 1)) + 1,
        ];
    }

    /**
     * Organic competitors (`domain_organic_organic`) for a domain.
     *
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     has_more: bool,
     *     limit: int,
     *     offset: int,
     *     page: int
     * }
     */
    public function getCompetitors(
        string $domain,
        string $database,
        int $limit = 10,
        int $offset = 0,
        ?string $sort = null,
    ): array {
        $limit = $this->limit($limit);
        $offset = max(0, $offset);

        $params = [
            'domain' => $domain,
            'database' => $this->databaseCode($database, null, self::TYPE_ORGANIC_COMPETITORS),
            'display_limit' => $offset + $limit,
            'display_offset' => $offset,
        ];

        if ($sort !== null && array_key_exists($sort, self::COMPETITOR_SORTS)) {
            $params['display_sort'] = self::COMPETITOR_SORTS[$sort];
        }

        $rows = $this->request(self::TYPE_ORGANIC_COMPETITORS, $params, self::COMPETITOR_COLUMNS);

        return [
            'rows' => array_map(fn (array $row) => [
                'domain' => $row['Dn'] ?? '',
                'relevance' => $row['Cr'] ?? null,
                'common_keywords' => $row['Np'] ?? null,
                'organic_keywords' => $row['Or'] ?? null,
                'organic_traffic' => $row['Ot'] ?? null,
                'organic_cost' => $row['Oc'] ?? null,
                'adwords_keywords' => $row['Ad'] ?? null,
            ], $rows),
            'has_more' => count($rows) >= $limit,
            'limit' => $limit,
            'offset' => $offset,
            'page' => intdiv($offset, max($limit, 1)) + 1,
        ];
    }

    /**
     * Backlink Overview (`backlinks_overview`) for a root domain.
     *
     * @return array<string, int|string|null>
     */
    public function getBacklinkOverview(string $domain): array
    {
        $row = $this->request(
            self::TYPE_BACKLINK_OVERVIEW,
            [
                'target' => $domain,
                'target_type' => 'root_domain',
            ],
            self::BACKLINK_COLUMNS,
        )[0] ?? [];

        return [
            'domain' => $domain,
            'authority_score' => $row['ascore'] ?? null,
            'total_backlinks' => $row['total'] ?? null,
            'referring_domains' => $row['domains_num'] ?? null,
            'referring_urls' => $row['urls_num'] ?? null,
            'referring_ips' => $row['ips_num'] ?? null,
            'referring_class_c_ips' => $row['ipclassc_num'] ?? null,
            'follow_links' => $row['follows_num'] ?? null,
            'nofollow_links' => $row['nofollows_num'] ?? null,
            'sponsored_links' => $row['sponsored_num'] ?? null,
            'ugc_links' => $row['ugc_num'] ?? null,
            'text_links' => $row['texts_num'] ?? null,
            'image_links' => $row['images_num'] ?? null,
            'form_links' => $row['forms_num'] ?? null,
            'frame_links' => $row['frames_num'] ?? null,
        ];
    }

    /**
     * Keyword Overview (`phrase_this`) for a single keyword.
     *
     * @return array{
     *     keyword: string,
     *     volume: int|null,
     *     cpc: float|null,
     *     competition: float|null,
     *     results: int|null,
     *     trends: array<int, float>,
     *     intent: string|null,
     *     difficulty: int|null
     * }
     */
    public function getKeywordOverview(string $keyword, string $database, ?string $device = null): array
    {
        if ($device === 'mobile') {
            throw new SemrushException(
                'Mobile databases are not available for keyword research.',
                'unsupported_device',
                422,
            );
        }

        $row = $this->request(
            self::TYPE_KEYWORD_OVERVIEW,
            [
                'phrase' => $keyword,
                'database' => $database,
            ],
            self::KEYWORD_OVERVIEW_COLUMNS,
        )[0] ?? [];

        return [
            'keyword' => $row['Ph'] ?? $keyword,
            'volume' => $row['Nq'] ?? null,
            'cpc' => $row['Cp'] ?? null,
            'competition' => $row['Co'] ?? null,
            'results' => $row['Nr'] ?? null,
            'trends' => $this->trends($row['Td'] ?? null),
            'intent' => $this->intentLabel($row['In'] ?? null),
            'difficulty' => $row['Kd'] ?? null,
        ];
    }

    /**
     * Send a request to Semrush, parse the semicolon-delimited CSV response,
     * and return the rows keyed by the requested export column codes.
     *
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    protected function request(string $type, array $params, array $columns): array
    {
        if (blank(config('semrush.api_key'))) {
            throw new SemrushException(
                'The Semrush API is not configured. Add SEMRUSH_API_KEY to your environment configuration.',
                'not_configured',
                503,
            );
        }

        return Cache::remember(
            $this->cacheKey($type, $params, $columns),
            (int) config('semrush.cache_ttl'),
            fn () => $this->fetch($type, $params, $columns),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    protected function fetch(string $type, array $params, array $columns): array
    {
        $endpoint = self::ENDPOINTS[$type];

        $query = [
            'type' => $type,
            'key' => config('semrush.api_key'),
            'export_columns' => implode(',', $columns),
            ...$params,
        ];

        if ($endpoint['escape']) {
            $query['export_decode'] = 1;
            $query['export_escape'] = 1;
        }

        $url = rtrim((string) config('semrush.api_url'), '/').'/'.ltrim($endpoint['path'], '/');

        try {
            $response = Http::timeout((int) config('semrush.timeout'))
                ->retry((int) config('semrush.retries'), 250, throw: false)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            $this->logFailure($type, $params, 'network_error', null, $e->getMessage());

            throw new SemrushException(
                'Could not reach the Semrush API. Please try again later.',
                'network_error',
                503,
                true,
            );
        } catch (Throwable $e) {
            $this->logFailure($type, $params, 'http_error', null, $e->getMessage());

            throw new SemrushException(
                'An unexpected error occurred while contacting the Semrush API.',
                'semrush_error',
                502,
                true,
            );
        }

        $body = $response->body();
        $errorCode = $this->errorCodeFromBody($body);

        if ($response->failed() || $errorCode !== null) {
            $code = $errorCode ?? "http_{$response->status()}";
            $this->logFailure($type, $params, (string) $code, $response->status());

            throw $this->translateError($type, $body, $response->status());
        }

        return $this->parseCsv($body, $columns);
    }

    /**
     * Parse a semicolon-delimited CSV payload into rows keyed by export code.
     *
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    protected function parseCsv(string $body, array $columns): array
    {
        $lines = array_values(array_filter(
            preg_split('/\r?\n/', trim($body)) ?: [],
            fn (string $line) => trim($line) !== '',
        ));

        $rows = [];

        foreach (array_slice($lines, 1) as $line) {
            $values = str_getcsv($line, ';', '"');

            $row = [];

            foreach ($columns as $index => $code) {
                $row[$code] = $this->castValue($values[$index] ?? null);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array{label: string, value: int}>
     */
    protected function positionDistribution(array $row): array
    {
        return array_map(
            fn (array $bucket) => [
                'label' => $bucket[1],
                'value' => (int) ($row[$bucket[0]] ?? 0),
            ],
            self::POSITION_BUCKETS,
        );
    }

    protected function databaseCode(string $database, ?string $device, string $type): string
    {
        if ($device !== 'mobile') {
            return $database;
        }

        if (! (self::ENDPOINTS[$type]['mobile'] ?? false)) {
            throw new SemrushException(
                'Mobile databases are not available for this report.',
                'unsupported_device',
                422,
            );
        }

        return 'mobile-'.$database;
    }

    /**
     * @return array<int, float>
     */
    protected function trends(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        return array_map('floatval', explode(',', $value));
    }

    protected function intentLabel(mixed $intent): ?string
    {
        return match ($intent) {
            0 => 'Commercial',
            1 => 'Informational',
            2 => 'Navigational',
            3 => 'Transactional',
            default => $intent !== null ? (string) $intent : null,
        };
    }

    protected function castValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    protected function limit(int $limit): int
    {
        return max(1, min($limit, (int) config('semrush.max_limit')));
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<int, string>  $columns
     */
    protected function cacheKey(string $type, array $params, array $columns): string
    {
        return 'semrush:'.sha1(serialize([$type, $params, $columns]));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function logFailure(
        string $type,
        array $params,
        string $errorCode,
        ?int $httpStatus = null,
        string $detail = '',
    ): void {
        Log::warning('Semrush API request failed', [
            'endpoint' => $type,
            'http_status' => $httpStatus,
            'error_code' => $errorCode,
            'detail' => $detail !== '' ? $detail : null,
            'context' => Arr::only($params, ['domain', 'database', 'phrase', 'target']),
        ]);
    }

    protected function errorCodeFromBody(string $body): ?int
    {
        if (preg_match('/ERROR\s+(\d{1,5})/', $body, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    protected function translateError(string $type, string $body, int $status): SemrushException
    {
        $code = $this->errorCodeFromBody($body);

        if ($code === null) {
            return match (true) {
                $status === 401 => new SemrushException(
                    'The Semrush API key is invalid or missing.',
                    'invalid_api_key',
                    401,
                ),
                $status === 403 => new SemrushException(
                    'Access to the Semrush API is denied for this subscription.',
                    'plan_restricted',
                    403,
                ),
                $status === 429 => new SemrushException(
                    'Semrush is rate limiting requests. Please try again in a moment.',
                    'rate_limited',
                    429,
                    true,
                ),
                $status >= 500 => new SemrushException(
                    'The Semrush API is temporarily unavailable. Please try again later.',
                    'semrush_unavailable',
                    503,
                    true,
                ),
                default => new SemrushException(
                    'Unable to retrieve data from Semrush.',
                    'semrush_error',
                    502,
                ),
            };
        }

        return match (true) {
            $code === 50 => new SemrushException(
                'No results were found for the requested domain or keyword.',
                'no_results',
                404,
            ),
            in_array($code, [110, 120, 402], true) => new SemrushException(
                'The Semrush API key is invalid. Check the SEMRUSH_API_KEY configuration.',
                'invalid_api_key',
                401,
            ),
            in_array($code, [130, 135], true) => new SemrushException(
                'The current Semrush subscription does not include access to this report.',
                'plan_restricted',
                403,
            ),
            in_array($code, [131, 134], true) => new SemrushException(
                'The Semrush report request limit has been reached. Please try again later.',
                'rate_limited',
                429,
                true,
            ),
            $code === 132 => new SemrushException(
                'Semrush API units are exhausted. Recharge your unit balance before continuing.',
                'insufficient_units',
                402,
            ),
            $code === 133 => $this->databaseAccessDenied($type),
            $code === 429 => new SemrushException(
                'Semrush is rate limiting requests. Please try again in a moment.',
                'rate_limited',
                429,
                true,
            ),
            $code === 500 => new SemrushException(
                'The Semrush API encountered an internal error. Please try again later.',
                'semrush_unavailable',
                503,
                true,
            ),
            in_array($code, [605, 613, 10010, 10011, 10012, 10013, 10042], true) => new SemrushException(
                'The requested Semrush parameters are invalid.',
                'invalid_parameters',
                422,
            ),
            default => new SemrushException(
                'Unable to retrieve data from Semrush.',
                'semrush_error',
                502,
            ),
        };
    }

    protected function databaseAccessDenied(string $type): SemrushException
    {
        if ($type === self::TYPE_BACKLINK_OVERVIEW) {
            return new SemrushException(
                'The Backlinks API is not included in your current Semrush plan. Contact Semrush to enable it.',
                'backlinks_unavailable',
                403,
            );
        }

        return new SemrushException(
            'The selected database is not available on the current Semrush plan.',
            'database_access_denied',
            403,
        );
    }
}
