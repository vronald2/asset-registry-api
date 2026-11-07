<?php

declare(strict_types=1);

namespace App\OpenApi\Extensions;

use App\OpenApi\ProblemDetailsSchema;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\RecordsNotFoundException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Documents the 404 of an unknown resource (e.g. a failed route model binding) as problem details.
 */
final class NotFoundProblemExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && (
                $type->isInstanceOf(RecordsNotFoundException::class)
                || $type->isInstanceOf(NotFoundHttpException::class)
            );
    }

    public function toResponse(Type $type): Response
    {
        return ProblemDetailsSchema::response(HttpResponse::HTTP_NOT_FOUND, 'Not found');
    }
}
