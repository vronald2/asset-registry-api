<?php

declare(strict_types=1);

namespace App\OpenApi\Extensions;

use App\OpenApi\ProblemDetailsSchema;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Documents validation failures as 422 problem details with the messages by field.
 */
final class ValidationProblemExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $type->isInstanceOf(ValidationException::class);
    }

    public function toResponse(Type $type): Response
    {
        return ProblemDetailsSchema::response(
            HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            'Validation error',
            withValidationErrors: true,
        );
    }
}
