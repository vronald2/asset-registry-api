<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base class of the expected (business) errors of the API.
 *
 * Every subclass declares its HTTP status and error code, so the central {@see ApiExceptionRenderer}
 * and the OpenAPI documentation handle new subclasses without any modification.
 * The message is returned to the client, so it must not contain internal details.
 */
abstract class ApiException extends RuntimeException
{
    /**
     * HTTP status code of the error response.
     */
    abstract public static function status(): int;

    /**
     * Machine-readable error code of the error response.
     */
    abstract public static function errorCode(): ErrorCode;
}
