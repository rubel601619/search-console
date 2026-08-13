<?php

namespace App\Services;

use Google\Client;

class GoogleClientFactory
{
    /**
     * Create a Google API client configured with the application credentials.
     *
     * @param  array<int, string>  $scopes
     */
    public function make(array $scopes = []): Client
    {
        $client = new Client;

        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));

        if ($scopes !== []) {
            $client->setScopes($scopes);
        }

        return $client;
    }
}
