<?php

declare(strict_types=1);

namespace App\Http\ProblemDetails;

use App\Exceptions\ApiException;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Maps any exception to the problem details document the API responds with.
 */
final readonly class ProblemDetailsFactory
{
    private const string CONFLICT_DETAIL = 'The request conflicts with an existing resource.';

    private const string INTERNAL_ERROR_DETAIL = 'An unexpected error occurred.';

    public function __construct(
        #[Config('app.debug')]
        private bool $debug = false,
    ) {
    }

    public function fromThrowable(Throwable $exception, ?string $instance = null): ProblemDetails
    {
        $problem = match (true) {
            $exception instanceof ValidationException => new ProblemDetails(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->getMessage(),
                $instance,
                ['errors' => $exception->errors()],
            ),
            $exception instanceof ApiException => new ProblemDetails(
                $exception::status(),
                $exception->getMessage(),
                $instance,
            ),
            // The unique indexes are the last line of defence against concurrent requests.
            $exception instanceof UniqueConstraintViolationException => new ProblemDetails(
                Response::HTTP_CONFLICT,
                self::CONFLICT_DETAIL,
                $instance,
            ),
            $exception instanceof HttpExceptionInterface => new ProblemDetails(
                $exception->getStatusCode(),
                $this->httpDetail($exception),
                $instance,
            ),
            default => new ProblemDetails(Response::HTTP_INTERNAL_SERVER_ERROR, self::INTERNAL_ERROR_DETAIL, $instance),
        };

        return $this->debug ? $problem->withExtension('debug', $this->debugInformation($exception)) : $problem;
    }

    /**
     * The headers an HTTP exception carries (e.g. "Allow" of a 405 or "Retry-After" of a 429 response).
     *
     * @return array<string, string|list<string>>
     */
    public function headersFor(Throwable $exception): array
    {
        if (! $exception instanceof HttpExceptionInterface) {
            return [];
        }

        /** @var array<string, string|list<string>> $headers */
        $headers = $exception->getHeaders();

        return $headers;
    }

    private function httpDetail(HttpExceptionInterface $exception): string
    {
        $previous = $exception->getPrevious();

        // Laravel turns a failed route model binding into a 404; name the model, but not its namespace.
        if ($previous instanceof ModelNotFoundException) {
            return sprintf('%s not found.', class_basename($previous->getModel()));
        }

        $status = $exception->getStatusCode();
        $message = $exception->getMessage();

        // Server-side messages may carry internals; client errors are produced by the framework itself.
        if ($status >= Response::HTTP_INTERNAL_SERVER_ERROR || $message === '') {
            return Response::$statusTexts[$status] ?? self::INTERNAL_ERROR_DETAIL;
        }

        return $message;
    }

    /**
     * @return array{exception: class-string, message: string, file: string, line: int}
     */
    private function debugInformation(Throwable $exception): array
    {
        return [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }
}
