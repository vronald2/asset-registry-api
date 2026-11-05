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

];
