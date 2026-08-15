<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Semrush SEO API
    |--------------------------------------------------------------------------
    |
    | Credentials and behaviour for the Semrush SEO API. The API key is only
    | ever used server-side and must never be exposed to the frontend, Inertia
    | props, or API responses.
    |
    */

    'api_key' => env('SEMRUSH_API_KEY'),

    'api_url' => env('SEMRUSH_API_URL', 'https://api.semrush.com/'),

    'api_balance_url' => env('SEMRUSH_API_BALANCE_URL', 'https://www.semrush.com/users/countapiunits.html'),

    /*
    | Request timeout in seconds.
    */
    'timeout' => (int) env('SEMRUSH_TIMEOUT', 15),

    /*
    | Automatic HTTP retries. Every retried request consumes API units, so this
    | defaults to 0. Rate-limit and error responses are surfaced instead of
    | being silently retried.
    */
    'retries' => (int) env('SEMRUSH_RETRIES', 0),

    /*
    | Seconds successful responses are cached. Caching avoids re-billing the
    | same data on repeated views and prevents unnecessary duplicate requests.
    */
    'cache_ttl' => (int) env('SEMRUSH_CACHE_TTL', 3600),

    /*
    | Result limits. These cap every request so a single call can never
    | accidentally bill large amounts of API units. Semrush charges per line
    | of data returned.
    */
    'default_limit' => (int) env('SEMRUSH_DEFAULT_LIMIT', 20),

    'max_limit' => (int) env('SEMRUSH_MAX_LIMIT', 100),

    /*
    | Regional databases supported by the Semrush SEO API. Mobile variants are
    | composed with the "mobile-" prefix (e.g. "mobile-us").
    */
    'databases' => [
        'us', 'uk', 'ca', 'ru', 'de', 'fr', 'es', 'it', 'br', 'au',
        'ar', 'be', 'ch', 'dk', 'fi', 'hk', 'ie', 'il', 'mx', 'nl',
        'no', 'pl', 'se', 'sg', 'tr', 'jp', 'in', 'hu', 'at', 'nz',
        'cl', 'co', 'cz', 'id', 'my', 'ph', 'pt', 'za', 'ae', 'ua',
        'ro', 'sa', 'kr', 'tw', 'th', 'vn', 'eg', 'ng', 'pk', 'bd',
    ],

];
