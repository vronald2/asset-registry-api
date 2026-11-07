<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Base class of the expected, domain-level failures of the API (e.g. deleting a device that is still in use).
 *
 * The HTTP status belongs to the exception type rather than to an instance, so the OpenAPI generator can document
 * it from the class alone. The message becomes the "detail" member of the problem details response. These failures
 * are part of the normal API behaviour, so they are not reported to the error log.
 */
abstract class ApiException extends RuntimeException implements ShouldntReport
{
    abstract public static function status(): int;
}
