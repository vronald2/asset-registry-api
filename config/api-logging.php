<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API traffic logging
    |--------------------------------------------------------------------------
    |
    | Every request matching the paths below is stored with its response in the
    | api_request_logs table (see App\Http\Middleware\LogApiTraffic). The entry
    | is written after the response has been sent.
    |
    */

    'enabled' => (bool) env('API_LOG_ENABLED', true),

    // Request::is() patterns of the logged requests.
    'paths' => ['api', 'api/*'],

    // Larger bodies are truncated before they are stored; a marker shows the original size.
    'max_body_bytes' => (int) env('API_LOG_MAX_BODY_BYTES', 65536),

    // The value stored instead of a sensitive one.
    'mask' => '***',

    // Headers whose values are never stored (case-insensitive). The php-auth-* entries are the pseudo
    // headers Symfony derives from the Authorization header (the decoded Basic/Digest credentials).
    'masked_headers' => [
        'authorization',
        'proxy-authorization',
        'php-auth-user',
        'php-auth-pw',
        'php-auth-digest',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
        'x-csrf-token',
        'x-xsrf-token',
    ],

    // Query and body fields whose values are never stored, at any depth: a key is masked
    // when it contains any of these fragments (case-insensitive).
    'masked_fields' => [
        'password',
        'token',
        'secret',
        'api_key',
        'apikey',
    ],

];
