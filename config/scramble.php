<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateDocs;
use App\OpenApi\ApiErrorResponseExtension;

/*
| OpenAPI documentation generated from the code by Scramble (https://scramble.dedoc.co).
| UI: /docs/api, specification: /docs/api.json. Options not listed here use the package defaults.
*/

return [

    // Routes under this path are documented.
    'api_path' => 'api',

    'api_domain' => null,

    // Target of `php artisan scramble:export` (composer docs:export).
    'export_path' => 'docs/openapi.json',

    'info' => [
        'version' => '1.0.0',

        'description' => <<<'MARKDOWN'
            REST API of an asset registry: **device types**, **contracts**, and the serial-numbered
            **device units** on the contracts.

            A device type can be on several contracts (and several times on one contract), but every
            unit has its own serial number. A unit (device type + serial number) can be on one contract
            at a time.

            ## Conventions

            - Successful responses wrap the payload in `data`. Lists are paginated (`page`, `per_page`
              up to 100) and also return `links` and `meta`.
            - Updates use `PUT` and replace the whole representation: omitted optional fields are cleared.
            - Every response has an `X-Request-Id` header. A well-formed incoming `X-Request-Id` is reused.
            - Requests are rate limited per client IP address (`429 too_many_requests`).

            ## Errors

            Every error has the same format; `errors` is present for validation errors only:

            ```json
            {
              "message": "The given data was invalid.",
              "code": "validation_failed",
              "errors": {"serial_number": ["The serial number field is required."]},
              "request_id": "01a1ac12-ff5a-738b-b881-9d3dd96ca433"
            }
            ```

            | HTTP | `code` | Meaning |
            |------|--------|---------|
            | 404 | `resource_not_found` | The requested resource does not exist. |
            | 404 | `route_not_found` | The requested endpoint does not exist. |
            | 405 | `method_not_allowed` | The HTTP method is not supported by the endpoint (see the `Allow` header). |
            | 409 | `device_in_use` | The device is assigned to a contract, so it cannot be deleted. |
            | 409 | `serial_number_already_assigned` | The unit is already on a contract (concurrent requests). |
            | 409 | `conflict` | The request conflicts with the current state of a resource. |
            | 422 | `validation_failed` | The request data is invalid. |
            | 429 | `too_many_requests` | Rate limit exceeded (see the `Retry-After` header). |
            | 500 | `internal_server_error` | Unexpected error; report it with the `request_id`. |
            MARKDOWN,
    ],

    'ui' => [
        'title' => 'Asset Registry API',
    ],

    'renderer' => 'elements',

    // Public URL, protected by HTTP Basic Auth (credentials: API_DOCS_USERNAME / API_DOCS_PASSWORD).
    'middleware' => [
        AuthenticateDocs::class,
    ],

    'extensions' => [
        ApiErrorResponseExtension::class,
    ],

];
