<?php

declare(strict_types=1);

namespace App\OpenApi\Extensions;

use App\Exceptions\ApiException;
use App\OpenApi\ProblemDetailsSchema;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Support\Str;

/**
 * Documents the domain failures of the API (App\Exceptions\ApiException subclasses, declared with @throws on the
 * controller actions) as problem details with the status of the exception type.
 */
final class ApiProblemExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $type->isInstanceOf(ApiException::class);
    }

    public function toResponse(Type $type): ?Response
    {
        if (! $type instanceof ObjectType || ! is_a($type->name, ApiException::class, true)) {
            return null;
        }

        return ProblemDetailsSchema::response($type->name::status(), $this->description($type->name));
    }

    /**
     * E.g. DeviceInUseException → "Device in use".
     *
     * @param class-string<ApiException> $exception
     */
    private function description(string $exception): string
    {
        return Str::ucfirst(Str::lower(Str::headline(Str::beforeLast(class_basename($exception), 'Exception'))));
    }
}
