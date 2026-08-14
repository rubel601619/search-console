<?php

namespace App\Services;

use App\Models\GoogleAccount;
use App\Models\User;
use Google\Client;
use Google\Service\SearchConsole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;

class GoogleAuthService
{
    /**
     * The Google scopes requested when connecting an account.
     *
     * @var array<int, string>
     */
    public const SCOPES = [
        SearchConsole::WEBMASTERS_READONLY,
    ];

    public function __construct(
        private readonly Socialite $socialite,
        private readonly GoogleClientFactory $clientFactory,
    ) {}

    /**
     * Redirect the authenticated user to Google's consent screen.
     */
    public function redirect(): RedirectResponse
    {
        return $this->driver()->redirect();
    }

    /**
     * Handle the OAuth callback and store or update the google account.
     */
    public function handleCallback(): GoogleAccount
    {
        $user = $this->driver()->user();

        if (! $user instanceof SocialiteUser) {
            throw new RuntimeException('The google provider returned an unexpected user.');
        }

        return $this->storeAccount($user);
    }

    /**
     * Remove the user's google account, revoking Google access when possible.
     */
    public function disconnect(User $user): void
    {
        $account = $user->googleAccount;

        if ($account === null) {
            return;
        }

        try {
            $client = $this->clientFactory->make();
            $client->setAccessToken($account->access_token);
            $client->revokeToken();
        } catch (\Throwable $e) {
            Log::warning('Failed to revoke Google access.', ['error' => $e->getMessage()]);
        }

        $account->delete();
    }

    /**
     * Get a Google API client authenticated with the account's access token.
     *
     * @throws RuntimeException when the access token cannot be refreshed
     */
    public function client(GoogleAccount $account): Client
    {
        if ($account->hasExpired()) {
            $this->refreshAccessToken($account);
        }

        $client = $this->clientFactory->make(self::SCOPES);
        $client->setAccessToken($account->access_token);

        return $client;
    }

    /**
     * Exchange the account's refresh token for a fresh access token.
     *
     * @throws RuntimeException when the account has no refresh token or Google rejects the request
     */
    public function refreshAccessToken(GoogleAccount $account): void
    {
        Cache::lock("google-token-refresh:{$account->getKey()}", 15)->block(5, function () use ($account): void {
            $account->refresh();

            if (! $account->hasExpired()) {
                return;
            }

            if ($account->refresh_token === null) {
                throw new RuntimeException('The google account has no refresh token to refresh with.');
            }

            $credentials = $this->clientFactory->make()
                ->fetchAccessTokenWithRefreshToken($account->refresh_token);

            if (isset($credentials['error']) || empty($credentials['access_token'])) {
                throw new RuntimeException(
                    $credentials['error_description'] ?? 'Google could not refresh the access token.',
                );
            }

            $updates = [
                'access_token' => $credentials['access_token'],
                'expires_at' => now()->addSeconds((int) ($credentials['expires_in'] ?? 3600)),
            ];

            if (! empty($credentials['refresh_token'])) {
                $updates['refresh_token'] = $credentials['refresh_token'];
            }

            $account->forceFill($updates)->save();
        });
    }

    /**
     * Persist or update the google account for the authenticated user.
     */
    protected function storeAccount(SocialiteUser $googleUser): GoogleAccount
    {
        $account = GoogleAccount::firstOrNew(['user_id' => Auth::id()]);

        $account->fill([
            'google_id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'access_token' => $googleUser->token,
            'expires_at' => (int) $googleUser->expiresIn > 0
                ? now()->addSeconds((int) $googleUser->expiresIn)
                : null,
            'scopes' => $googleUser->approvedScopes ?: self::SCOPES,
        ]);

        if (! empty($googleUser->refreshToken)) {
            $account->refresh_token = $googleUser->refreshToken;
        }

        $account->save();

        return $account;
    }

    /**
     * Get a Socialite google provider configured for this application.
     */
    protected function driver(): GoogleProvider
    {
        $clientId = (string) config('services.google.client_id');
        $clientSecret = (string) config('services.google.client_secret');
        $redirectUri = (string) config('services.google.redirect');

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('Google OAuth credentials are not configured.');
        }

        $driver = $this->socialite->driver('google');

        if (! $driver instanceof GoogleProvider) {
            throw new RuntimeException('The google socialite driver is not available.');
        }

        return $driver
            ->redirectUrl($redirectUri)
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
            ])
            ->scopes(self::SCOPES);
    }
}
