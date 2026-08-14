<?php

namespace App\Services;

use App\Exceptions\SearchConsoleException;
use App\Models\User;
use App\Support\SearchConsoleQuery;
use Closure;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\ApiDataRow;
use Google\Service\SearchConsole\ApiDimensionFilter;
use Google\Service\SearchConsole\ApiDimensionFilterGroup;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Google\Service\SearchConsole\WmxSite;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Throwable;

class SearchConsoleService
{
    public const DIMENSION_DATE = 'DATE';

    public const DIMENSION_QUERY = 'QUERY';

    public const DIMENSION_PAGE = 'PAGE';

    public const DIMENSION_COUNTRY = 'COUNTRY';

    public const DIMENSION_DEVICE = 'DEVICE';

    public const DIMENSION_SEARCH_APPEARANCE = 'SEARCH_APPEARANCE';

    public function __construct(
        private readonly GoogleAuthService $googleAuthService,
    ) {}

    /**
     * List the Search Console properties the user has access to.
     *
     * @return array<int, array{url: string, permission_level: string}>
     */
    public function getProperties(User $user): array
    {
        $response = $this->withService($user, fn (SearchConsole $service) => $service->sites->listSites());

        return array_map(
            fn (WmxSite $site) => [
                'url' => $site->getSiteUrl(),
                'permission_level' => $site->getPermissionLevel(),
            ],
            $response->getSiteEntry() ?? [],
        );
    }

    /**
     * Aggregate metrics and daily breakdown for the dashboard overview.
     *
     * @return array{
     *     totals: array{clicks: int, impressions: int, ctr: float, position: float},
     *     previous: ?array{clicks: int, impressions: int, ctr: float, position: float},
     *     comparison: ?array{clicks: ?float, impressions: ?float, ctr: ?float, position: ?float},
     *     daily: array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>
     * }
     */
    public function getAnalytics(User $user, SearchConsoleQuery $query): array
    {
        $current = $this->runDimensionQuery(
            $user,
            self::DIMENSION_DATE,
            $query->withRowLimit(max($query->daysInRange(), $query->rowLimit)),
        );

        $totals = $this->summarize($current['rows']);
        $previous = null;
        $comparison = null;

        if ($query->daysInRange() > 0) {
            $previousPeriod = $query->previousPeriod();

            $previous = $this->summarize(
                $this->runDimensionQuery(
                    $user,
                    self::DIMENSION_DATE,
                    $previousPeriod->withRowLimit($previousPeriod->daysInRange()),
                )['rows'],
            );

            $comparison = $this->compare($totals, $previous);
        }

        return [
            'totals' => $totals,
            'previous' => $previous,
            'comparison' => $comparison,
            'daily' => $current['rows'],
        ];
    }

    /**
     * @return array{
     *     rows: array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     has_more: bool,
     *     page: int
     * }
     */
    public function getTopQueries(User $user, SearchConsoleQuery $query): array
    {
        return $this->runDimensionQuery($user, self::DIMENSION_QUERY, $query);
    }

    /**
     * @return array{
     *     rows: array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     has_more: bool,
     *     page: int
     * }
     */
    public function getTopPages(User $user, SearchConsoleQuery $query): array
    {
        return $this->runDimensionQuery($user, self::DIMENSION_PAGE, $query);
    }

    /**
     * @return array{
     *     rows: array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     has_more: bool,
     *     page: int
     * }
     */
    public function getCountries(User $user, SearchConsoleQuery $query): array
    {
        return $this->runDimensionQuery($user, self::DIMENSION_COUNTRY, $query);
    }

    /**
     * @return array{
     *     rows: array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     has_more: bool,
     *     page: int
     * }
     */
    public function getDevices(User $user, SearchConsoleQuery $query): array
    {
        return $this->runDimensionQuery($user, self::DIMENSION_DEVICE, $query);
    }

    /**
     * @return array{
     *     rows: array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     has_more: bool,
     *     page: int
     * }
     */
    public function getSearchAppearance(User $user, SearchConsoleQuery $query): array
    {
        return $this->runDimensionQuery($user, self::DIMENSION_SEARCH_APPEARANCE, $query);
    }

    /**
     * @return array{
     *     rows: array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>,
     *     has_more: bool,
     *     page: int
     * }
     */
    protected function runDimensionQuery(User $user, string $dimension, SearchConsoleQuery $query): array
    {
        $request = new SearchAnalyticsQueryRequest;

        $request->setStartDate($query->startDate);
        $request->setEndDate($query->endDate);
        $request->setDimensions([$dimension]);
        $request->setRowLimit($query->rowLimit);
        $request->setStartRow($query->startRow);
        $request->setType(SearchAnalyticsQueryRequest::TYPE_WEB);
        $request->setAggregationType(SearchAnalyticsQueryRequest::AGGREGATION_TYPE_AUTO);
        $request->setDimensionFilterGroups($this->dimensionFilterGroups($query));

        $response = $this->withService(
            $user,
            fn (SearchConsole $service) => $service->searchanalytics->query($query->property, $request),
        );

        $rows = $response->getRows() ?? [];

        return [
            'rows' => array_map(
                fn (ApiDataRow $row) => [
                    'key' => $row->getKeys()[0] ?? '',
                    'clicks' => (int) $row->getClicks(),
                    'impressions' => (int) $row->getImpressions(),
                    'ctr' => round((float) $row->getCtr(), 4),
                    'position' => round((float) $row->getPosition(), 2),
                ],
                $rows,
            ),
            'has_more' => count($rows) >= $query->rowLimit,
            'page' => intdiv($query->startRow, max($query->rowLimit, 1)) + 1,
        ];
    }

    /**
     * @return array<int, ApiDimensionFilterGroup>
     */
    protected function dimensionFilterGroups(SearchConsoleQuery $query): array
    {
        $dimensionFilters = [];

        $dimensions = [
            'country' => ApiDimensionFilter::DIMENSION_COUNTRY,
            'device' => ApiDimensionFilter::DIMENSION_DEVICE,
            'page' => ApiDimensionFilter::DIMENSION_PAGE,
            'query' => ApiDimensionFilter::DIMENSION_QUERY,
        ];

        foreach ($dimensions as $name => $dimension) {
            $expression = match ($name) {
                'country' => $query->country,
                'device' => $query->device,
                'page' => $query->page,
                'query' => $query->query,
            };

            if ($expression === null || $expression === '') {
                continue;
            }

            $filter = new ApiDimensionFilter;
            $filter->setDimension($dimension);
            $filter->setExpression($expression);
            $filter->setOperator(ApiDimensionFilter::OPERATOR_EQUALS);

            $dimensionFilters[] = $filter;
        }

        if ($dimensionFilters === []) {
            return [];
        }

        $group = new ApiDimensionFilterGroup;
        $group->setGroupType(ApiDimensionFilterGroup::GROUP_TYPE_AND);
        $group->setFilters($dimensionFilters);

        return [$group];
    }

    /**
     * @param  array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>  $rows
     * @return array{clicks: int, impressions: int, ctr: float, position: float}
     */
    protected function summarize(array $rows): array
    {
        $clicks = 0;
        $impressions = 0;
        $weightedPosition = 0;

        foreach ($rows as $row) {
            $clicks += $row['clicks'];
            $impressions += $row['impressions'];
            $weightedPosition += $row['position'] * $row['impressions'];
        }

        return [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0.0,
            'position' => $impressions > 0 ? round($weightedPosition / $impressions, 2) : 0.0,
        ];
    }

    /**
     * @param  array{clicks: int, impressions: int, ctr: float, position: float}  $current
     * @param  array{clicks: int, impressions: int, ctr: float, position: float}  $previous
     * @return array{clicks: ?float, impressions: ?float, ctr: ?float, position: ?float}
     */
    protected function compare(array $current, array $previous): array
    {
        return [
            'clicks' => $this->percentageChange($current['clicks'], $previous['clicks']),
            'impressions' => $this->percentageChange($current['impressions'], $previous['impressions']),
            'ctr' => $this->percentageChange($current['ctr'], $previous['ctr']),
            'position' => $this->percentageChange($current['position'], $previous['position']),
        ];
    }

    protected function percentageChange(float $current, float $previous): ?float
    {
        if ($previous == 0) {
            return $current == 0 ? 0.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 2);
    }

    protected function withService(User $user, Closure $callback): mixed
    {
        try {
            $service = $this->serviceFor($user);

            return $callback($service);
        } catch (SearchConsoleException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $this->translateException($e);
        }
    }

    protected function serviceFor(User $user): SearchConsole
    {
        $account = $user->googleAccount;

        if ($account === null) {
            throw new SearchConsoleException(
                'Connect your Google account to view Search Console data.',
                'not_connected',
                401,
            );
        }

        return new SearchConsole($this->googleAuthService->client($account));
    }

    protected function translateException(Throwable $e): SearchConsoleException
    {
        if ($e instanceof GoogleServiceException) {
            $reason = $this->googleErrorReason($e);

            if (
                $e->getCode() === 429
                || $reason === 'rateLimitExceeded'
                || $reason === 'userRateLimitExceeded'
                || $reason === 'quotaExceeded'
            ) {
                return new SearchConsoleException(
                    'Google is rate limiting requests. Please try again in a moment.',
                    'rate_limited',
                    429,
                    true,
                );
            }

            if ($e->getCode() === 401 || $e->getCode() === 403) {
                return new SearchConsoleException(
                    'Your Google authorization has expired or been revoked. Reconnect your account.',
                    'unauthorized',
                    401,
                );
            }

            if ($e->getCode() === 404 || $reason === 'invalidSite' || $reason === 'invalid_site') {
                return new SearchConsoleException(
                    'The selected Search Console property is invalid or no longer available.',
                    'invalid_property',
                    404,
                );
            }

            return new SearchConsoleException(
                'Google returned an error: '.($e->getMessage() !== '' ? $e->getMessage() : 'unknown error'),
                'search_console_error',
                422,
                true,
            );
        }

        if ($e instanceof GuzzleException) {
            return new SearchConsoleException(
                'Could not reach the Google Search Console API. Please try again later.',
                'network_error',
                503,
                true,
            );
        }

        if ($e instanceof RuntimeException) {
            return new SearchConsoleException(
                'Your Google authorization has expired or been revoked. Reconnect your account.',
                'unauthorized',
                401,
            );
        }

        return new SearchConsoleException(
            'An unexpected error occurred while contacting Google Search Console.',
            'search_console_error',
            422,
            true,
        );
    }

    protected function googleErrorReason(GoogleServiceException $e): string
    {
        $errors = $e->getErrors();

        return isset($errors[0]['reason']) ? (string) $errors[0]['reason'] : '';
    }
}
