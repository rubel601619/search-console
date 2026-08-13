<?php

use App\Models\GoogleAccount;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\GoogleClientFactory;
use Google\Client;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function fakeGoogleProvider(?SocialiteUser $user = null, ?string $redirectUrl = null): MockInterface
{
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('with')->andReturnSelf();
    $provider->shouldReceive('scopes')->andReturnSelf();

    if ($user !== null) {
        $provider->shouldReceive('user')->once()->andReturn($user);
    }

    if ($redirectUrl !== null) {
        $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse($redirectUrl));
    }

    test()->mock(Socialite::class, function (MockInterface $mock) use ($provider) {
        $mock->shouldReceive('driver')->once()->with('google')->andReturn($provider);
    });

    return $provider;
}

test('guests cannot connect, callback, or disconnect a google account', function () {
    $this->get(route('google.connect'))->assertRedirect(route('login'));
    $this->get(route('google.callback'))->assertRedirect(route('login'));
    $this->post(route('google.disconnect'))->assertRedirect(route('login'));
});

test('authenticated users are redirected to google to connect', function () {
    fakeGoogleProvider(redirectUrl: 'https://accounts.google.com/o/oauth2/auth');

    $this->actingAs($this->user)
        ->get(route('google.connect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('the google callback stores the account details', function () {
    fakeGoogleProvider(user: SocialiteUser::fake([
        'id' => 'google-user-123',
        'email' => 'google@example.com',
        'token' => 'secret-access-token',
        'refreshToken' => 'secret-refresh-token',
        'expiresIn' => 3600,
        'approvedScopes' => GoogleAuthService::SCOPES,
    ]));

    $this->actingAs($this->user)
        ->get(route('google.callback'))
        ->assertRedirect(route('dashboard'));

    $account = $this->user->googleAccount;

    expect($account)->not->toBeNull()
        ->and($account->google_id)->toBe('google-user-123')
        ->and($account->email)->toBe('google@example.com')
        ->and($account->scopes)->toBe(GoogleAuthService::SCOPES)
        ->and($account->expires_at)->not->toBeNull()
        ->and($account->refresh_token)->toBe('secret-refresh-token');

    $this->assertDatabaseMissing('google_accounts', ['access_token' => 'secret-access-token'])
        ->assertDatabaseMissing('google_accounts', ['refresh_token' => 'secret-refresh-token']);
});

test('the google callback updates an existing account instead of creating a duplicate', function () {
    GoogleAccount::factory()->for($this->user)->create([
        'google_id' => 'google-user-123',
        'email' => 'old@example.com',
    ]);

    fakeGoogleProvider(user: SocialiteUser::fake([
        'id' => 'google-user-123',
        'email' => 'new@example.com',
        'token' => 'new-access-token',
        'refreshToken' => 'new-refresh-token',
        'expiresIn' => 3600,
        'approvedScopes' => GoogleAuthService::SCOPES,
    ]));

    $this->actingAs($this->user)
        ->get(route('google.callback'))
        ->assertRedirect(route('dashboard'));

    expect(GoogleAccount::count())->toBe(1)
        ->and($this->user->fresh()->googleAccount->email)->toBe('new@example.com')
        ->and($this->user->fresh()->googleAccount->access_token)->toBe('new-access-token');
});

test('an authenticated user can disconnect their google account', function () {
    GoogleAccount::factory()->for($this->user)->create();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken')->once();
    $client->shouldReceive('revokeToken')->once()->andReturn(true);

    $this->mock(GoogleClientFactory::class, function (MockInterface $mock) use ($client) {
        $mock->shouldReceive('make')->once()->andReturn($client);
    });

    $this->actingAs($this->user)
        ->post(route('google.disconnect'))
        ->assertRedirect();

    expect($this->user->fresh()->googleAccount)->toBeNull();
});

test('an expired access token is refreshed when building the google client', function () {
    $account = GoogleAccount::factory()->for($this->user)->create([
        'expires_at' => now()->subMinute(),
        'refresh_token' => 'refresh-token',
        'access_token' => 'expired-access-token',
    ]);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('fetchAccessTokenWithRefreshToken')->once()
        ->with('refresh-token')
        ->andReturn(['access_token' => 'refreshed-access-token', 'expires_in' => 3600]);
    $client->shouldReceive('setAccessToken')->once();

    $this->mock(GoogleClientFactory::class, function (MockInterface $mock) use ($client) {
        $mock->shouldReceive('make')->twice()->andReturn($client);
    });

    $authService = app(GoogleAuthService::class);

    $authService->client($account);

    expect($account->fresh()->access_token)->toBe('refreshed-access-token')
        ->and($account->fresh()->hasExpired())->toBeFalse();
});

test('refreshing fails when the account has no refresh token', function () {
    $account = GoogleAccount::factory()->for($this->user)->create([
        'expires_at' => now()->subMinute(),
        'refresh_token' => null,
    ]);

    $this->mock(GoogleClientFactory::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('make');
    });

    app(GoogleAuthService::class)->client($account);
})->throws(RuntimeException::class);
