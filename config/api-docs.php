<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API documentation access
    |--------------------------------------------------------------------------
    |
    | HTTP Basic Auth credentials of the OpenAPI documentation (/docs/api and
    | /docs/api.json, see App\Http\Middleware\AuthenticateApiDocs). If either
    | value is empty, every request to the documentation is rejected.
    |
    */

    'username' => (string) env('API_DOCS_USERNAME', ''),

    'password' => (string) env('API_DOCS_PASSWORD', ''),

    'realm' => 'Asset Registry API documentation',

];
