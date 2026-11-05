<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every exception of the API in one uniform JSON format:
 *
 *     {"message": "...", "code": "...", "errors": {...}, "request_id": "..."}
 *
 * `errors` is present for validation errors only. Server errors never expose internal details,
 * except for the `debug` block that is added while APP_DEBUG is enabled.
 */
final readonly class ApiExceptionRenderer
{
    private const int DEBUG_TRACE_FRAMES = 15;

    public function __construct(
        private Context $context,
        #[Config('app.debug')] private bool $debug,
    ) {
    }

    /**
     * Returns null for exceptions that carry an intentionally built response of their own.
     */
    public function render(Throwable $exception): ?JsonResponse
    {
        if ($exception instanceof HttpResponseException) {
            return null;
        }

        $error = $this->describe($exception);

        $payload = [
            'message' => $error->message,
            'code' => $error->code->value,
        ];

        if ($error->errors !== []) {
            $payload['errors'] = $error->errors;
        }

        $payload['request_id'] = $this->context->get(AssignRequestId::CONTEXT_KEY);

        if ($this->debug && $error->status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $payload['debug'] = $this->debugDetails($exception);
        }

        return new JsonResponse($payload, $error->status, $error->headers);
    }

    /**
     * Note: Laravel has already converted ModelNotFoundException into NotFoundHttpException at this point.
     */
    private function describe(Throwable $exception): ApiError
    {
        return match (true) {
            $exception instanceof ValidationException => $this->describeValidationFailure($exception),
            $exception instanceof ApiException => new ApiError(
                status: $exception::status(),
                code: $exception::errorCode(),
                message: $exception->getMessage(),
            ),
            $exception instanceof NotFoundHttpException => $this->describeNotFound($exception),
            $exception instanceof UniqueConstraintViolationException => new ApiError(
                status: Response::HTTP_CONFLICT,
                code: ErrorCode::Conflict,
                message: 'The request conflicts with the current state of the resource.',
            ),
            $exception instanceof HttpExceptionInterface => $this->describeHttpException($exception),
            default => new ApiError(
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                code: ErrorCode::InternalServerError,
                message: 'An unexpected error occurred.',
            ),
        };
    }

    private function describeValidationFailure(ValidationException $exception): ApiError
    {
        /** @var array<string, list<string>> $errors */
        $errors = $exception->errors();

        return new ApiError(
            status: $exception->status,
            code: ErrorCode::ValidationFailed,
            message: 'The given data was invalid.',
            errors: $errors,
        );
    }

    private function describeNotFound(NotFoundHttpException $exception): ApiError
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof ModelNotFoundException) {
            return new ApiError(
                status: Response::HTTP_NOT_FOUND,
                code: ErrorCode::ResourceNotFound,
                // e.g. "Contract device not found."
                message: sprintf('%s not found.', Str::ucfirst(Str::snake(class_basename($previous->getModel()), ' '))),
            );
        }

        return new ApiError(
            status: Response::HTTP_NOT_FOUND,
            code: ErrorCode::RouteNotFound,
            message: 'The requested endpoint does not exist.',
        );
    }

    private function describeHttpException(HttpExceptionInterface $exception): ApiError
    {
        $status = $exception->getStatusCode();
        $message = $exception->getMessage();

        if ($message === '' || $status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $message = Response::$statusTexts[$status] ?? 'HTTP error.';
        }

        /** @var array<string, string|list<string>> $headers */
        $headers = $exception->getHeaders();

        return new ApiError(
            status: $status,
            code: ErrorCode::fromStatus($status),
            message: $message,
            headers: $headers,
        );
    }

    /**
     * @return array{exception: class-string<Throwable>, message: string, file: string, line: int, trace: list<string>}
     */
    private function debugDetails(Throwable $exception): array
    {
        $trace = array_map(
            static fn (array $frame): string => sprintf('%s:%s', $frame['file'] ?? '[internal]', $frame['line'] ?? '-'),
            array_slice($exception->getTrace(), 0, self::DEBUG_TRACE_FRAMES),
        );

        return [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
        ];
    }
}
