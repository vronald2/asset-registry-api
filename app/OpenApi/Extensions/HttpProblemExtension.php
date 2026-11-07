<?php

declare(strict_types=1);

namespace App\OpenApi\Extensions;

use App\OpenApi\ProblemDetailsSchema;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\HttpExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Documents the HTTP exceptions as problem details. Status code and description are resolved by Scramble's own
 * extension; only the response body differs.
 */
final class HttpProblemExtension extends HttpExceptionToResponseExtension
{
    public function toResponse(Type $type): ?Response
    {
        $response = parent::toResponse($type);

        if (! $response instanceof Response || ! is_int($response->code)) {
            return null;
        }

        return ProblemDetailsSchema::response($response->code, $response->description);
    }
}
