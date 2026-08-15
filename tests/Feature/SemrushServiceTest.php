<?php

use App\Exceptions\SemrushException;
use App\Services\Semrush\SemrushService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('semrush.api_key', 'test-key');
});

function semrushQuery(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

    return $params;
}

test('getDomainOverview parses the csv and maps the columns', function () {
    Http::fake([
        'api.semrush.com/*' => Http::response(
            "Domain;Rank;Organic Keywords;Organic Traffic;Organic Keywords Cost;Adwords Keywords;Adwords Traffic;Adwords Cost;#1;#2;#3;#4;#5;#6;#7;#8;#9;#10;#11\n"
            .'example.com;12345;980;54000;125.00;120;2400;60.00;1;2;3;4;5;6;7;8;9;10;11',
        ),
    ]);

    $result = app(SemrushService::class)->getDomainOverview('example.com', 'us', 'desktop');

    expect($result['domain'])->toBe('example.com')
        ->and($result['rank'])->toBe(12345)
        ->and($result['organic_keywords'])->toBe(980)
        ->and($result['organic_traffic'])->toBe(54000)
        ->and($result['organic_cost'])->toBe(125.0)
        ->and($result['adwords_keywords'])->toBe(120)
        ->and($result['position_distribution'])->toBe([
            ['label' => '1-3', 'value' => 1],
            ['label' => '4-10', 'value' => 2],
            ['label' => '11-20', 'value' => 3],
            ['label' => '21-30', 'value' => 4],
            ['label' => '31-40', 'value' => 5],
            ['label' => '41-50', 'value' => 6],
            ['label' => '51-60', 'value' => 7],
            ['label' => '61-70', 'value' => 8],
            ['label' => '71-80', 'value' => 9],
            ['label' => '81-90', 'value' => 10],
            ['label' => '91-100', 'value' => 11],
        ]);
});

test('getDomainOverview requests a mobile database when requested', function () {
    Http::fake([
        'api.semrush.com/*' => Http::response("Domain;Rank\nn/a;0"),
    ]);

    app(SemrushService::class)->getDomainOverview('example.com', 'us', 'mobile');

    Http::assertSent(fn ($request) => semrushQuery($request->url())['database'] === 'mobile-us');
});

test('getOrganicKeywords sends pagination, sorting, and escape parameters', function () {
    Http::fake([
        'api.semrush.com/*' => Http::response(
            "Keyword;Position\n"
            ."foo bar;1\nbar baz;2",
        ),
    ]);

    app(SemrushService::class)->getOrganicKeywords(
        'example.com',
        'us',
        'desktop',
        limit: 10,
        offset: 10,
        sort: '-volume',
    );

    Http::assertSent(function ($request) {
        $data = semrushQuery($request->url());

        return Str::startsWith($request->url(), 'https://api.semrush.com/')
            && $data['type'] === 'domain_organic'
            && $data['domain'] === 'example.com'
            && $data['database'] === 'us'
            && $data['display_limit'] === '20'
            && $data['display_offset'] === '10'
            && $data['display_sort'] === 'nq_desc'
            && $data['export_decode'] === '1'
            && $data['export_escape'] === '1';
    });
});

test('getOrganicKeywords maps rows, trends, and intent labels', function () {
    Http::fake([
        'api.semrush.com/*' => Http::response(
            "Keyword;Position;Previous Position;Position Difference;Search Volume;CPC;URL;Traffic(%);Traffic Cost(%);Competition;Number of Results;Trend;KD;Intent;Features Found for Domain;Features Found for Keyword;Position Type;Traffic\n"
            .'foo bar;3;5;-2;1200;1.25;https://example.com/foo;12.5;15.5;0.2;2500000;100,120,150;35;1;..;..;new;4500',
        ),
    ]);

    $result = app(SemrushService::class)->getOrganicKeywords('example.com', 'us');

    expect($result['rows'][0]['keyword'])->toBe('foo bar')
        ->and($result['rows'][0]['position'])->toBe(3)
        ->and($result['rows'][0]['previous_position'])->toBe(5)
        ->and($result['rows'][0]['position_difference'])->toBe(-2)
        ->and($result['rows'][0]['volume'])->toBe(1200)
        ->and($result['rows'][0]['cpc'])->toBe(1.25)
        ->and($result['rows'][0]['url'])->toBe('https://example.com/foo')
        ->and($result['rows'][0]['traffic_percent'])->toBe(12.5)
        ->and($result['rows'][0]['competition'])->toBe(0.2)
        ->and($result['rows'][0]['results'])->toBe(2500000)
        ->and($result['rows'][0]['trends'])->toBe([100.0, 120.0, 150.0])
        ->and($result['rows'][0]['difficulty'])->toBe(35)
        ->and($result['rows'][0]['intent'])->toBe('Informational')
        ->and($result['rows'][0]['traffic'])->toBe(4500)
        ->and($result['has_more'])->toBeFalse()
        ->and($result['page'])->toBe(1);
});

test('getOrganicKeywords reports has_more when a full page is returned', function () {
    $rows = array_map(
        fn (int $i) => "keyword $i;".($i + 1),
        range(1, 10),
    );

    Http::fake([
        'api.semrush.com/*' => Http::response("Keyword;Position\n".implode("\n", $rows)),
    ]);

    $result = app(SemrushService::class)->getOrganicKeywords('example.com', 'us', limit: 10);

    expect($result['rows'])->toHaveCount(10)
        ->and($result['has_more'])->toBeTrue();
});

test('getCompetitors maps the competitor columns', function () {
    Http::fake([
        'api.semrush.com/*' => Http::response(
            "Domain;Competition Level;Number of Common Keywords;Organic Keywords;Organic Traffic;Organic Keywords Cost;Adwords Keywords\n"
            .'competitor.com;0.8;1450;3200;120000;3500.00;800',
        ),
    ]);

    $result = app(SemrushService::class)->getCompetitors('example.com', 'us');

    expect($result['rows'][0]['domain'])->toBe('competitor.com')
        ->and($result['rows'][0]['relevance'])->toBe(0.8)
        ->and($result['rows'][0]['common_keywords'])->toBe(1450)
        ->and($result['rows'][0]['organic_keywords'])->toBe(3200)
        ->and($result['rows'][0]['organic_traffic'])->toBe(120000)
        ->and($result['rows'][0]['organic_cost'])->toBe(3500.0)
        ->and($result['rows'][0]['adwords_keywords'])->toBe(800);
});

test('getKeywordOverview parses the keyword report', function () {
    Http::fake([
        'api.semrush.com/*' => Http::response(
            "Keyword;Search Volume;CPC;Competition;Number of Results;Trend;Intent;KD\n"
            .'foo bar;1200;1.25;0.2;2500000;100,120,150;1;35',
        ),
    ]);

    $result = app(SemrushService::class)->getKeywordOverview('foo bar', 'us', 'desktop');

    expect($result['keyword'])->toBe('foo bar')
        ->and($result['volume'])->toBe(1200)
        ->and($result['cpc'])->toBe(1.25)
        ->and($result['competition'])->toBe(0.2)
        ->and($result['results'])->toBe(2500000)
        ->and($result['trends'])->toBe([100.0, 120.0, 150.0])
        ->and($result['intent'])->toBe('Informational')
        ->and($result['difficulty'])->toBe(35);
});

test('getKeywordOverview rejects mobile databases', function () {
    expect(fn () => app(SemrushService::class)->getKeywordOverview('foo bar', 'us', 'mobile'))
        ->toThrow(function (SemrushException $e) {
            expect($e->errorCode())->toBe('unsupported_device')
                ->and($e->statusCode())->toBe(422);
        });
});

test('getBacklinkOverview hits the analytics endpoint without escape parameters', function () {
    Http::fake([
        'api.semrush.com/analytics/v1/*' => Http::response(
            "AScore;Total;Referring Domains;Referring URLs;IPs;Class C IPs;Follows;No Follows;Sponsored;UGC;Texts;Images;Forms;Frames\n"
            .'45;12500;320;1800;210;40;9500;2900;350;120;7800;40;60;20',
        ),
    ]);

    $result = app(SemrushService::class)->getBacklinkOverview('example.com');

    expect($result['authority_score'])->toBe(45)
        ->and($result['total_backlinks'])->toBe(12500)
        ->and($result['referring_domains'])->toBe(320)
        ->and($result['referring_urls'])->toBe(1800)
        ->and($result['referring_ips'])->toBe(210)
        ->and($result['referring_class_c_ips'])->toBe(40)
        ->and($result['follow_links'])->toBe(9500)
        ->and($result['nofollow_links'])->toBe(2900)
        ->and($result['text_links'])->toBe(7800)
        ->and($result['frame_links'])->toBe(20);

    Http::assertSent(function ($request) {
        $data = semrushQuery($request->url());

        return Str::startsWith($request->url(), 'https://api.semrush.com/analytics/v1/')
            && $data['type'] === 'backlinks_overview'
            && $data['target'] === 'example.com'
            && $data['target_type'] === 'root_domain'
            && ! array_key_exists('export_decode', $data)
            && ! array_key_exists('export_escape', $data);
    });
});

test('successful responses are cached and do not re-bill units', function () {
    Http::fake([
        'api.semrush.com/*' => Http::response("Domain;Rank\nexample.com;42"),
    ]);

    $service = app(SemrushService::class);

    $service->getDomainOverview('example.com', 'us');
    $service->getDomainOverview('example.com', 'us');

    Http::assertSentCount(1);
});

test('different databases produce different cache entries', function () {
    Http::fake([
        'api.semrush.com/*' => Http::response("Domain;Rank\nexample.com;42"),
    ]);

    $service = app(SemrushService::class);

    $service->getDomainOverview('example.com', 'us');
    $service->getDomainOverview('example.com', 'uk');

    Http::assertSentCount(2);
});

test('semrush error codes are translated to typed exceptions', function (string $body, string $code, int $status, bool $retryable) {
    Http::fake([
        'api.semrush.com/*' => Http::response($body, 200),
    ]);

    expect(fn () => app(SemrushService::class)->getDomainOverview('error.example', 'us'))
        ->toThrow(function (SemrushException $e) use ($code, $status, $retryable) {
            expect($e->errorCode())->toBe($code)
                ->and($e->statusCode())->toBe($status)
                ->and($e->retryable())->toBe($retryable);
        });
})->with([
    'no results' => ['ERROR 50: No more than 1,000 results for one search', 'no_results', 404, false],
    'invalid key' => ['ERROR 110: Invalid API key', 'invalid_api_key', 401, false],
    'plan restricted' => ['ERROR 130: The API key has no rights', 'plan_restricted', 403, false],
    'rate limited' => ['ERROR 131: The report request limit is reached', 'rate_limited', 429, true],
    'insufficient units' => ['ERROR 132: You do not have enough API Units to make this request', 'insufficient_units', 402, false],
    'server error' => ['ERROR 500: Server error', 'semrush_unavailable', 503, true],
]);

test('backlinks 133 is translated to a backlinks_unavailable error', function () {
    Http::fake([
        'api.semrush.com/analytics/v1/*' => Http::response('ERROR 133: The current plan does not include the backlinks database'),
    ]);

    expect(fn () => app(SemrushService::class)->getBacklinkOverview('example.com'))
        ->toThrow(function (SemrushException $e) {
            expect($e->errorCode())->toBe('backlinks_unavailable')
                ->and($e->statusCode())->toBe(403);
        });
});

test('an http 429 without a semrush code is retryable', function () {
    Http::fake([
        'api.semrush.com/*' => Http::response('Too Many Requests', 429),
    ]);

    expect(fn () => app(SemrushService::class)->getDomainOverview('error.example', 'us'))
        ->toThrow(function (SemrushException $e) {
            expect($e->errorCode())->toBe('rate_limited')
                ->and($e->retryable())->toBeTrue();
        });
});

test('a blank api key throws a not_configured error before any request', function () {
    config()->set('semrush.api_key', '');

    Http::fake([
        'api.semrush.com/*' => Http::response('n/a'),
    ]);

    Http::assertNothingSent();

    expect(fn () => app(SemrushService::class)->getDomainOverview('example.com', 'us'))
        ->toThrow(function (SemrushException $e) {
            expect($e->errorCode())->toBe('not_configured')
                ->and($e->statusCode())->toBe(503);
        });

    Http::assertNothingSent();
});

test('getApiBalance parses the units from the balance endpoint', function () {
    Http::fake([
        'www.semrush.com/*' => Http::response('1,000'),
    ]);

    $result = app(SemrushService::class)->getApiBalance();

    expect($result['units_left'])->toBe(1000)
        ->and($result['units_used'])->toBeNull();

    Http::assertSent(fn ($request) => Str::startsWith($request->url(), 'https://www.semrush.com/users/countapiunits.html'));
});

test('getApiBalance tolerates a failed balance request', function () {
    Http::fake([
        'www.semrush.com/*' => Http::response('', 500),
    ]);

    $result = app(SemrushService::class)->getApiBalance();

    expect($result)->toBe(['units_left' => null, 'units_used' => null]);
});

test('getApiBalance returns null units without a request when no api key is set', function () {
    config()->set('semrush.api_key', '');

    $result = app(SemrushService::class)->getApiBalance();

    expect($result)->toBe(['units_left' => null, 'units_used' => null]);

    Http::assertNothingSent();
});
