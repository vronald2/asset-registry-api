<?php

declare(strict_types=1);

namespace App\OpenApi;

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\ErrorCode;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType as ObjectSchema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Documents the errors detected by Scramble (validation, route model binding, `@throws` tags) in the
 * uniform format of {@see ApiExceptionRenderer}, instead of Laravel's default error format.
 *
 * Custom extensions take precedence over Scramble's built-in ones.
 */
final class ApiErrorResponseExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $this->describe($type) !== null;
    }

    public function toResponse(Type $type): ?Response
    {
        $error = $type instanceof ObjectType ? $this->describe($type) : null;

        if ($error === null) {
            return null;
        }

        [$status, $code, $description] = $error;

        $body = new ObjectSchema();
        $body->addProperty('message', $this->string('Human-readable error message.'));
        $body->addProperty('code', $this->string('Machine-readable error code.')->enum([$code->value]));
        $required = ['message', 'code'];

        if ($code === ErrorCode::ValidationFailed) {
            $body->addProperty('errors', $this->fieldErrors());
            $required[] = 'errors';
        }

        $body->addProperty('request_id', $this->string('Identifier of the request (also in the X-Request-Id header).'));
        $required[] = 'request_id';
        $body->setRequired($required);

        /** @var Schema $schema the factory method has no return type */
        $schema = Schema::fromType($body);

        return Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', $schema);
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('responses', Str::start($type->name, '\\'), $this->components);
    }

    private function string(string $description): StringType
    {
        $type = new StringType();
        $type->setDescription($description);

        return $type;
    }

    private function fieldErrors(): ObjectSchema
    {
        $messages = new ArrayType();
        $messages->setItems(new StringType());

        $errors = new ObjectSchema();
        $errors->setDescription('The validation messages of each invalid field.');
        $errors->additionalProperties($messages);

        return $errors;
    }

    /**
     * @return array{int, ErrorCode, string}|null status, error code and description
     */
    private function describe(ObjectType $type): ?array
    {
        $class = $type->name;

        return match (true) {
            $type->isInstanceOf(ValidationException::class) => [
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                ErrorCode::ValidationFailed,
                'Validation error',
            ],
            is_a($class, ApiException::class, true) => [
                $class::status(),
                $class::errorCode(),
                Str::ucfirst(Str::snake(Str::beforeLast(class_basename($class), 'Exception'), ' ')),
            ],
            $type->isInstanceOf(RecordsNotFoundException::class),
            $type->isInstanceOf(NotFoundHttpException::class) => [
                HttpResponse::HTTP_NOT_FOUND,
                ErrorCode::ResourceNotFound,
                'Resource not found',
            ],
            default => null,
        };
    }
}
