<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Exceptions\ApiException;
use App\Http\ProblemDetails\ProblemDetailsFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use PDOException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class ProblemDetailsFactoryTest extends TestCase
{
    private ProblemDetailsFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new ProblemDetailsFactory(debug: false);
    }

    public function testValidationExceptionBecomesUnprocessableContentWithFieldErrors(): void
    {
        $exception = ValidationException::withMessages(['name' => ['The name field is required.']]);

        $problem = $this->factory->fromThrowable($exception, '/api/v1/devices')->toArray();

        self::assertSame(422, $problem['status']);
        self::assertSame('Unprocessable Content', $problem['title']);
        self::assertSame('about:blank', $problem['type']);
        self::assertSame('/api/v1/devices', $problem['instance']);
        self::assertSame(['name' => ['The name field is required.']], $problem['errors']);
    }

    public function testModelNotFoundNamesTheModelWithoutItsNamespace(): void
    {
        $missing = (new ModelNotFoundException())->setModel(User::class, [5]);

        $problem = $this->factory->fromThrowable(new NotFoundHttpException('No query results.', $missing));

        self::assertSame(404, $problem->status);
        self::assertSame('User not found.', $problem->detail);
    }

    public function testClientHttpExceptionKeepsItsStatusMessageAndHeaders(): void
    {
        $exception = new MethodNotAllowedHttpException(['GET', 'HEAD'], 'The PATCH method is not supported.');

        $problem = $this->factory->fromThrowable($exception);

        self::assertSame(405, $problem->status);
        self::assertSame('The PATCH method is not supported.', $problem->detail);
        self::assertSame(['Allow' => 'GET, HEAD'], $this->factory->headersFor($exception));
    }

    public function testServerHttpExceptionHidesItsMessage(): void
    {
        $problem = $this->factory->fromThrowable(new HttpException(503, 'Database host 10.0.0.5 is down.'));

        self::assertSame(503, $problem->status);
        self::assertSame('Service Unavailable', $problem->detail);
    }

    public function testApiExceptionUsesItsStatusAndMessage(): void
    {
        $exception = new class ('The device is still in use.') extends ApiException {
            public static function status(): int
            {
                return 409;
            }
        };

        $problem = $this->factory->fromThrowable($exception);

        self::assertSame(409, $problem->status);
        self::assertSame('Conflict', $problem->title());
        self::assertSame('The device is still in use.', $problem->detail);
    }

    public function testUniqueConstraintViolationBecomesConflict(): void
    {
        $exception = new UniqueConstraintViolationException(
            'mysql',
            'insert into contract_device ...',
            [],
            new PDOException('Duplicate entry'),
        );

        $problem = $this->factory->fromThrowable($exception);

        self::assertSame(409, $problem->status);
        self::assertSame('The request conflicts with an existing resource.', $problem->detail);
    }

    public function testUnexpectedExceptionBecomesGenericInternalServerError(): void
    {
        $problem = $this->factory->fromThrowable(new RuntimeException('Secret internal state'))->toArray();

        self::assertSame(500, $problem['status']);
        self::assertSame('An unexpected error occurred.', $problem['detail']);
        self::assertArrayNotHasKey('debug', $problem);
        self::assertStringNotContainsString('Secret internal state', (string) json_encode($problem));
    }

    public function testDebugModeAddsExceptionDetails(): void
    {
        $exception = new RuntimeException('Secret internal state');

        $problem = (new ProblemDetailsFactory(debug: true))->fromThrowable($exception)->toArray();

        self::assertSame(
            [
                'exception' => RuntimeException::class,
                'message' => 'Secret internal state',
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ],
            $problem['debug'],
        );
    }
}
