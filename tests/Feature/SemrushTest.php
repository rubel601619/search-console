<?php

use App\Exceptions\SemrushException;
use App\Models\User;
use App\Services\Semrush\SemrushService;
use Mockery\MockInterface;

beforeEach(function () {
    config()->set('semrush.api_key', 'test-key');
});

test('the semrush page exposes configuration status and databases', function () {
    $this->withoutVite();

    $this->actingAs(User::factory()->create())
        ->get(route('semrush'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Semrush/Index')
            ->where('configured', true)
            ->has('databases'));
});

test('the semrush page requires authentication', function () {
    $this->withoutVite();

    $this->get(route('semrush'))
        ->assertRedirect(route('login'));
});

test('every semrush api endpoint requires authentication', function () {
    foreach (['balance', 'domain-overview', 'organic-keywords', 'competitors', 'backlinks', 'keyword-overview'] as $endpoint) {
        $this->getJson(route("semrush.$endpoint"))
            ->assertStatus(401);
    }
});

test('domain reports reject invalid domains and databases', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('semrush.domain-overview'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('domain');

    $this->actingAs(User::factory()->create())
        ->getJson(route('semrush.domain-overview', [
            'domain' => 'not a valid domain',
            'database' => 'xx',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['domain', 'database']);
});

test('the domain overview endpoint normalizes the domain and forwards the filters', function () {
    $this->mock(SemrushService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getDomainOverview')
        ->once()
        ->with('www.example.com', 'us', 'desktop')
        ->andReturn(['domain' => 'www.example.com']));

    $this->actingAs(User::factory()->create())
        ->getJson(route('semrush.domain-overview', [
            'domain' => 'https://www.Example.com:8080/path?q=1',
            'database' => 'us',
        ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.domain', 'www.example.com')
        ->assertJsonPath('meta.domain', 'www.example.com');
});

test('the organic keywords endpoint forwards pagination and sorting', function () {
    $this->mock(SemrushService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getOrganicKeywords')
        ->once()
        ->with('example.com', 'us', 'mobile', 10, 20, '-volume')
        ->andReturn(['rows' => [], 'has_more' => false, 'limit' => 10, 'offset' => 20, 'page' => 3]));

    $this->actingAs(User::factory()->create())
        ->getJson(route('semrush.organic-keywords', [
            'domain' => 'example.com',
            'database' => 'us',
            'device' => 'mobile',
            'limit' => 10,
            'offset' => 20,
            'sort' => '-volume',
        ]))
        ->assertOk()
        ->assertJsonPath('data.page', 3)
        ->assertJsonPath('meta.sort', '-volume');
});

test('the competitors endpoint forwards pagination and sorting', function () {
    $this->mock(SemrushService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getCompetitors')
        ->once()
        ->with('example.com', 'us', 20, 40, 'relevance')
        ->andReturn(['rows' => [], 'has_more' => true, 'limit' => 20, 'offset' => 40, 'page' => 3]));

    $this->actingAs(User::factory()->create())
        ->getJson(route('semrush.competitors', [
            'domain' => 'example.com',
            'database' => 'us',
            'limit' => 20,
            'offset' => 40,
            'sort' => 'relevance',
        ]))
        ->assertOk()
        ->assertJsonPath('data.has_more', true);
});

test('the backlinks endpoint forwards the domain', function () {
    $this->mock(SemrushService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getBacklinkOverview')
        ->once()
        ->with('example.com')
        ->andReturn(['domain' => 'example.com', 'authority_score' => 45]));

    $this->actingAs(User::factory()->create())
        ->getJson(route('semrush.backlinks', ['domain' => 'example.com']))
        ->assertOk()
        ->assertJsonPath('data.authority_score', 45);
});

test('the keyword overview endpoint forwards the keyword and database', function () {
    $this->mock(SemrushService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getKeywordOverview')
        ->once()
        ->with('foo bar', 'us', 'desktop')
        ->andReturn(['keyword' => 'foo bar', 'volume' => 1200]));

    $this->actingAs(User::factory()->create())
        ->getJson(route('semrush.keyword-overview', [
            'keyword' => 'foo bar',
            'database' => 'us',
        ]))
        ->assertOk()
        ->assertJsonPath('data.volume', 1200);
});

test('the balance endpoint returns the api units', function () {
    $this->mock(SemrushService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getApiBalance')
        ->once()
        ->andReturn(['units_left' => 1000, 'units_used' => 200]));

    $this->actingAs(User::factory()->create())
        ->getJson(route('semrush.balance'))
        ->assertOk()
        ->assertJsonPath('data.units_left', 1000);
});

test('service exceptions become standardized json errors', function () {
    $this->mock(SemrushService::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('getDomainOverview')
        ->once()
        ->andThrow(new SemrushException(
            'Semrush API units are exhausted. Recharge your unit balance before continuing.',
            'insufficient_units',
            402,
        )));

    $this->actingAs(User::factory()->create())
        ->getJson(route('semrush.domain-overview', [
            'domain' => 'example.com',
            'database' => 'us',
        ]))
        ->assertStatus(402)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data', null)
        ->assertJsonPath('meta.code', 'insufficient_units')
        ->assertJsonPath('meta.retryable', false);
});
