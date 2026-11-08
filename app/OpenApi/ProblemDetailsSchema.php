<?php

declare(strict_types=1);

namespace App\OpenApi;

use App\Http\ProblemDetails\ProblemDetails;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The OpenAPI description of the problem details responses the API sends (see App\Http\ProblemDetails).
 */
final class ProblemDetailsSchema
{
    public static function response(
        int $status,
        ?string $description = null,
        bool $withValidationErrors = false,
    ): Response {
        $title = HttpResponse::$statusTexts[$status] ?? 'Error';

        $body = new ObjectType();
        $body->addProperty(
            'type',
            self::describe(new StringType(), 'The problem type; always "about:blank".', ProblemDetails::DEFAULT_TYPE),
        );
        $body->addProperty('title', self::describe(new StringType(), 'The HTTP status phrase.', $title));
        $body->addProperty('status', self::describe(new IntegerType(), 'The HTTP status code.', $status));
        $body->addProperty('detail', self::describe(new StringType(), 'What went wrong, for this occurrence.'));
        $body->addProperty(
            'instance',
            self::describe(new StringType(), 'Path of the request that caused the problem.', '/api/v1/devices/1'),
        );
        $required = ['type', 'title', 'status', 'detail', 'instance'];

        if ($withValidationErrors) {
            $messages = new ArrayType();
            $messages->setItems(new StringType());
            $errors = new ObjectType();
            $errors->setDescription('The validation messages, by field.');
            $errors->additionalProperties($messages);
            $body->addProperty('errors', $errors);
            $required[] = 'errors';
        }

        $body->setRequired($required);

        /** @var Schema $schema The vendor method is not typed, it returns a new Schema. */
        $schema = Schema::fromType($body);

        return Response::make($status)
            ->setDescription($description ?? $title)
            ->setContent(ProblemDetails::CONTENT_TYPE, $schema);
    }

    private static function describe(Type $type, string $description, int|string|null $example = null): Type
    {
        $type->setDescription($description);

        if ($example !== null) {
            $type->example($example);
        }

        return $type;
    }
}
