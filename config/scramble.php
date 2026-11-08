<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateApiDocs;
use App\OpenApi\Extensions\ApiProblemExtension;
use App\OpenApi\Extensions\HttpProblemExtension;
use App\OpenApi\Extensions\NotFoundProblemExtension;
use App\OpenApi\Extensions\ValidationProblemExtension;

/*
 * OpenAPI documentation generated from the code by Scramble: UI at /docs/api, JSON at /docs/api.json.
 * Only the settings that differ from the package defaults (vendor/dedoc/scramble/config/scramble.php) are listed.
 */
return [

    // The versioned API; with a wildcard pattern the documented paths stay complete (/api/v1/...).
    'api_path' => 'api/v*',

    'info' => [
        'version' => '1.0.0',
        'description' => implode("\n", [
            'REST API of the asset registry: contracts and the devices (device types) assigned to them.',
            '',
            '## Conventions',
            '',
            '- Requests and responses are JSON.',
            '- Lists are paginated with `page` (from 1) and `per_page` (1–100, default 15); the response carries'
            . ' `links` and `meta`.',
            '- `PUT` replaces a resource (the optional fields left out are cleared), `PATCH` changes only the fields'
            . ' that are sent.',
            '- A device type can be on any number of contracts, at most once on the same contract. Every assignment'
            . ' has a serial number that is unique across the registry (case-insensitive).',
            '',
            '## Versioning',
            '',
            'Every endpoint is under the `/api/v1` prefix. A backwards-incompatible change would be released under a'
            . ' new prefix (`/api/v2`), so that existing clients keep working.',
            '',
            '## Errors',
            '',
            'Every error is an [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) problem details document'
            . ' (`application/problem+json`) with the `type`, `title`, `status`, `detail` and `instance` members.'
            . ' Validation errors (422) add `errors`, the messages by field.'
            . ' A malformed request gets 400: an invalid URL encoding, invalid UTF-8 in the input, or a JSON body that'
            . ' is neither a JSON object nor empty.'
            . ' A POST, PUT or PATCH body that looks like JSON but is not sent with a JSON Content-Type gets 415.',
            '',
            '## Traffic log',
            '',
            'Every request to `/api/*` is stored with its response in the `api_request_logs` table;'
            . ' credentials and other secrets are masked.',
        ]),
    ],

    'ui' => [
        'title' => 'Asset Registry API',
    ],

    // HTTP Basic Auth instead of the package's environment based access check.
    'middleware' => [
        AuthenticateApiDocs::class,
    ],

    // The errors are documented in the problem details format the API actually responds with.
    'extensions' => [
        ValidationProblemExtension::class,
        HttpProblemExtension::class,
        NotFoundProblemExtension::class,
        ApiProblemExtension::class,
    ],

];
