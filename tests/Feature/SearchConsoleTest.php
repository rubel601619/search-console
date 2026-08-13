<?php

use App\Exceptions\SearchConsoleException;
use App\Models\GoogleAccount;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\SearchConsoleService;
use App\Support\SearchConsoleQuery;
use Google\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery\MockInterface;

function googleClientResponding(array $responses, array &$history = []): Client
{
    $mock = new MockHandler(array_map(
        fn ($response) => $response instanceof Throwable
            ? $response
            : new Response($response['status'] ?? 200, [], json_encode($response['body'] ?? [])),
        $responses,
    ));

    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    $client = new Client;
    $client->setAccessToken('test-access-token');
    $client->setHttpClient(new GuzzleClient(['handler' => $handlerStack]));

    return $client;
}

function mockGoogleAuthClient(Client $client): void
{
    test()->mock(GoogleAuthService::class, fn (MockInterface $mock) => $mock->shouldReceive('client')->andReturn($client));
}

function connectedUser(): User
{
    $user = User::factory()->create();

    GoogleAccount::factory()->for($user)->create();

    return $user;
}

beforeEach(function () {
    $this->user = connectedUser();
});

test('every search console endpoint requires a connected google account', function () {
    $user = User::factory()->create();

    foreach (['properties', 'analytics', 'queries', 'pages', 'countries', 'devices', 'search-appearance'] as $endpoint) {
        $this->actingAs($user)
            ->getJson(route("search-console.$endpoint", ['property' => 'sc-domain:example.com']))
            ->assertStatus(401)
            ->assertJsonPath('code', 'not_connected');
    }
});

test('every search console endpoint requires authentication', function () {
    foreach (['properties', 'analytics', 'queries', 'pages', 'countries', 'devices', 'search-appearance'] as $endpoint) {
        $this->getJson(route("search-console.$endpoint"))
            ->assertStatus(401);
    }
});

test('the properties endpoint returns the mapped site entries', function () {
    $this->mock(SearchConsoleService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getProperties')
        ->once()
        ->andReturn([
            ['url' => 'sc-domain:example.com', 'permission_level' => 'siteFullUser'],
            ['url' => 'https://example.com/', 'permission_level' => 'siteOwner'],
        ]));

    $this->actingAs($this->user)
        ->getJson(route('search-console.properties'))
        ->assertOk()
        ->assertJsonPath('data.0.url', 'sc-domain:example.com')
        ->assertJsonPath('data.1.permission_level', 'siteOwner');
});

test('the analytics endpoint forwards the filters to the service', function () {
    $this->mock(SearchConsoleService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getAnalytics')
        ->once()
        ->with(Mockery::on(fn ($user) => $user instanceof User), Mockery::on(
            fn (SearchConsoleQuery $query) => $query->property === 'sc-domain:example.com'
                && $query->startDate === '2026-07-01'
                && $query->endDate === '2026-07-31'
                && $query->country === 'USA'
                && $query->device === 'MOBILE'
                && $query->page === 'https://example.com/'
                && $query->query === 'foo bar',
        ))
        ->andReturn(['totals' => [], 'previous' => null, 'comparison' => null, 'daily' => []]));

    $this->actingAs($this->user)
        ->getJson(route('search-console.analytics', [
            'property' => 'sc-domain:example.com',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'country' => 'USA',
            'device' => 'MOBILE',
            'page_url' => 'https://example.com/',
            'query' => 'foo bar',
        ]))
        ->assertOk()
        ->assertJsonPath('data.totals', []);
});

test('validation errors are returned for missing properties', function () {
    $this->actingAs($this->user)
        ->getJson(route('search-console.queries'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('property');
});

test('service exceptions become standardized json errors', function () {
    $this->mock(SearchConsoleService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getTopQueries')
        ->once()
        ->andThrow(new SearchConsoleException('Google is rate limiting requests. Please try again in a moment.', 'rate_limited', 429, true)));

    $this->actingAs($this->user)
        ->getJson(route('search-console.queries', ['property' => 'sc-domain:example.com']))
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited')
        ->assertJsonPath('retryable', true);
});

test('getProperties maps the response site entries', function () {
    mockGoogleAuthClient(googleClientResponding([
        ['body' => ['siteEntry' => [
            ['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteFullUser'],
            ['siteUrl' => 'https://example.com/', 'permissionLevel' => 'siteOwner'],
        ]]],
    ]));

    $properties = app(SearchConsoleService::class)->getProperties($this->user);

    expect($properties)->toBe([
        ['url' => 'sc-domain:example.com', 'permission_level' => 'siteFullUser'],
        ['url' => 'https://example.com/', 'permission_level' => 'siteOwner'],
    ]);
});

test('getAnalytics aggregates totals, daily rows, and comparison against the previous period', function () {
    $history = [];

    mockGoogleAuthClient(googleClientResponding([
        ['body' => ['rows' => [
            ['keys' => ['2026-07-01'], 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 2.0],
            ['keys' => ['2026-07-02'], 'clicks' => 20, 'impressions' => 200, 'ctr' => 0.1, 'position' => 3.0],
        ]]],
        ['body' => ['rows' => [
            ['keys' => ['2026-06-03'], 'clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 2.0],
        ]]],
    ], $history));

    $analytics = app(SearchConsoleService::class)->getAnalytics(
        $this->user,
        new SearchConsoleQuery(
            property: 'sc-domain:example.com',
            startDate: '2026-07-01',
            endDate: '2026-07-31',
        ),
    );

    expect($analytics['totals'])->toBe(['clicks' => 30, 'impressions' => 300, 'ctr' => 0.1, 'position' => 2.67])
        ->and($analytics['previous']['clicks'])->toBe(5)
        ->and($analytics['comparison']['clicks'])->toBe(500.0)
        ->and($analytics['comparison']['position'])->toBe(33.5)
        ->and($analytics['daily'])->toHaveCount(2)
        ->and($analytics['daily'][0]['key'])->toBe('2026-07-01');

    expect(count($history))->toBe(2)
        ->and(json_decode((string) $history[0]['request']->getBody(), true)['dimensions'])->toBe(['DATE'])
        ->and(json_decode((string) $history[1]['request']->getBody(), true)['startDate'])->toBe('2026-05-31');
});

test('dimension queries send the dimension, pagination, and dimension filters', function () {
    $history = [];

    mockGoogleAuthClient(googleClientResponding([
        ['body' => ['rows' => [['keys' => ['foo bar'], 'clicks' => 1, 'impressions' => 10, 'ctr' => 0.1, 'position' => 4.0]]]],
    ], $history));

    $result = app(SearchConsoleService::class)->getTopQueries(
        $this->user,
        new SearchConsoleQuery(
            property: 'sc-domain:example.com',
            startDate: '2026-07-01',
            endDate: '2026-07-31',
            country: 'USA',
            device: 'MOBILE',
            page: 'https://example.com/',
            query: 'foo bar',
            rowLimit: 10,
            startRow: 10,
        ),
    );

    $body = json_decode((string) $history[0]['request']->getBody(), true);

    expect($body['dimensions'])->toBe(['QUERY'])
        ->and($body['rowLimit'])->toBe(10)
        ->and($body['startRow'])->toBe(10)
        ->and($body['type'])->toBe('WEB')
        ->and($body['dimensionFilterGroups'][0]['groupType'])->toBe('AND')
        ->and($body['dimensionFilterGroups'][0]['filters'])->toHaveCount(4)
        ->and($body['dimensionFilterGroups'][0]['filters'][0]['dimension'])->toBe('COUNTRY')
        ->and($body['dimensionFilterGroups'][0]['filters'][1]['dimension'])->toBe('DEVICE')
        ->and($body['dimensionFilterGroups'][0]['filters'][2]['dimension'])->toBe('PAGE')
        ->and($body['dimensionFilterGroups'][0]['filters'][3]['dimension'])->toBe('QUERY')
        ->and($body['dimensionFilterGroups'][0]['filters'][0]['operator'])->toBe('EQUALS')
        ->and($result['page'])->toBe(2);
});

test('dimension queries report has_more based on the returned row count', function () {
    mockGoogleAuthClient(googleClientResponding([
        ['body' => ['rows' => array_map(
            fn (int $i) => ['keys' => ["query-$i"], 'clicks' => 1, 'impressions' => 10, 'ctr' => 0.1, 'position' => 4.0],
            range(1, 10),
        )]],
    ]));

    $result = app(SearchConsoleService::class)->getTopQueries(
        $this->user,
        new SearchConsoleQuery(
            property: 'sc-domain:example.com',
            startDate: '2026-07-01',
            endDate: '2026-07-31',
            rowLimit: 10,
        ),
    );

    expect($result['has_more'])->toBeTrue()
        ->and($result['rows'])->toHaveCount(10);
});

test('a forbidden response is translated to an unauthorized error', function () {
    mockGoogleAuthClient(googleClientResponding([
        ['status' => 403, 'body' => ['error' => [
            'code' => 403,
            'message' => 'Forbidden',
            'errors' => [['reason' => 'forbidden']],
        ]]],
    ]));

    expect(fn () => app(SearchConsoleService::class)->getProperties($this->user))
        ->toThrow(function (SearchConsoleException $e) {
            expect($e->errorCode())->toBe('unauthorized')
                ->and($e->statusCode())->toBe(401)
                ->and($e->retryable())->toBeFalse();
        });
});

test('a rate limited response is translated to a retryable rate_limited error', function () {
    mockGoogleAuthClient(googleClientResponding([
        ['status' => 429, 'body' => ['error' => [
            'code' => 429,
            'message' => 'Rate limit exceeded',
            'errors' => [['reason' => 'userRateLimitExceeded']],
        ]]],
    ]));

    expect(fn () => app(SearchConsoleService::class)->getProperties($this->user))
        ->toThrow(function (SearchConsoleException $e) {
            expect($e->errorCode())->toBe('rate_limited')
                ->and($e->statusCode())->toBe(429)
                ->and($e->retryable())->toBeTrue();
        });
});

test('an invalid property response is translated to an invalid_property error', function () {
    mockGoogleAuthClient(googleClientResponding([
        ['status' => 404, 'body' => ['error' => [
            'code' => 404,
            'message' => 'Site not found',
            'errors' => [['reason' => 'invalidSite']],
        ]]],
    ]));

    expect(fn () => app(SearchConsoleService::class)->getProperties($this->user))
        ->toThrow(function (SearchConsoleException $e) {
            expect($e->errorCode())->toBe('invalid_property')
                ->and($e->statusCode())->toBe(404);
        });
});

test('a failed token refresh is translated to an unauthorized error', function () {
    test()->mock(GoogleAuthService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('client')
        ->andThrow(new RuntimeException('The google account has no refresh token to refresh with.')));

    expect(fn () => app(SearchConsoleService::class)->getProperties($this->user))
        ->toThrow(function (SearchConsoleException $e) {
            expect($e->errorCode())->toBe('unauthorized')
                ->and($e->statusCode())->toBe(401);
        });
});

test('a network failure is translated to a retryable network_error', function () {
    $request = new Request('GET', 'https://searchconsole.googleapis.com/');
    mockGoogleAuthClient(googleClientResponding([new ConnectException('Connection timed out', $request)]));

    expect(fn () => app(SearchConsoleService::class)->getProperties($this->user))
        ->toThrow(function (SearchConsoleException $e) {
            expect($e->errorCode())->toBe('network_error')
                ->and($e->statusCode())->toBe(503)
                ->and($e->retryable())->toBeTrue();
        });
});
