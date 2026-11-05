<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Stable, machine-readable error codes of the API (the `code` field of every error response).
 */
enum ErrorCode: string
{
    case ValidationFailed = 'validation_failed';
    case ResourceNotFound = 'resource_not_found';
    case RouteNotFound = 'route_not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case DeviceInUse = 'device_in_use';
    case SerialNumberAlreadyAssigned = 'serial_number_already_assigned';
    case Conflict = 'conflict';
    case TooManyRequests = 'too_many_requests';
    case BadRequest = 'bad_request';
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
    case HttpError = 'http_error';
    case InternalServerError = 'internal_server_error';
    case ServiceUnavailable = 'service_unavailable';

    /**
     * The code of a generic HTTP error with the given status.
     */
    public static function fromStatus(int $status): self
    {
        return match ($status) {
            400 => self::BadRequest,
            401 => self::Unauthenticated,
            403 => self::Forbidden,
            404 => self::RouteNotFound,
            405 => self::MethodNotAllowed,
            409 => self::Conflict,
            422 => self::ValidationFailed,
            429 => self::TooManyRequests,
            503 => self::ServiceUnavailable,
            default => $status >= 500 ? self::InternalServerError : self::HttpError,
        };
    }
}
