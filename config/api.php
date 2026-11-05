<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default and maximum page size of the list endpoints (`per_page` query parameter).
    |
    */

    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of API requests per minute and client IP address.
    |
    */

    'rate_limit' => [
        'per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Traffic logging
    |--------------------------------------------------------------------------
    |
    | Every request matching one of the path patterns is stored, together with its response,
    | in the `api_request_logs` table. Values of the listed keys (request/response body fields,
    | query parameters and headers, case-insensitive) are masked before they are stored.
    |
    */

    'logging' => [
        'enabled' => (bool) env('API_LOGGING_ENABLED', true),

        'paths' => ['api/*'],

        'masked_keys' => [
            'authorization',
            'proxy-authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-xsrf-token',
            'password',
            'password_confirmation',
            'current_password',
            'secret',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
        ],

        'mask' => '********',

        // Request and response bodies longer than this (in characters) are truncated.
        'max_body_length' => 65_535,

        // Entries older than this are removed by `php artisan model:prune`.
        'retention_days' => (int) env('API_LOGGING_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | API documentation
    |--------------------------------------------------------------------------
    |
    | HTTP Basic Auth credentials of the OpenAPI documentation. While either value is empty,
    | access to the documentation is denied.
    |
    */

    'docs' => [
        'username' => (string) env('API_DOCS_USERNAME', ''),
        'password' => (string) env('API_DOCS_PASSWORD', ''),
    ],

];
