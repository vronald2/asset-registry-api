<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\DeviceInUseException;
use App\Http\Middleware\AssignRequestId;
use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Validation\ValidationException;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;
use Throwable;

/**
 * Uses the framework test case: validation exceptions need the validator from the container.
 */
final class ApiExceptionRendererTest extends TestCase
{
    #[Test]
    public function rendersValidationErrorsWithTheFieldMessages(): void
    {
        $response = $this->render(ValidationException::withMessages(['name' => ['The name field is required.']]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'message' => 'The given data was invalid.',
            'code' => 'validation_failed',
            'errors' => ['name' => ['The name field is required.']],
            'request_id' => 'test-request-id',
        ], $response->getData(true));
    }

    #[Test]
    public function rendersBusinessExceptionsWithTheirOwnStatusAndCode(): void
    {
        $device = (new Device())->forceFill(['id' => 7]);

        $response = $this->render(DeviceInUseException::forDevice($device));

        $this->assertError($response, 409, 'device_in_use');
        $this->assertSame(
            'Device 7 is assigned to at least one contract. Remove it from the contracts before deleting it.',
            $this->field($response, 'message'),
        );
    }

    #[Test]
    public function rendersMissingModelsAsResourceNotFound(): void
    {
        $previous = (new ModelNotFoundException())->setModel(ContractDevice::class, [5]);

        $response = $this->render(new NotFoundHttpException('Not found.', $previous));

        $this->assertError($response, 404, 'resource_not_found');
        $this->assertSame('Contract device not found.', $this->field($response, 'message'));
    }

    #[Test]
    public function rendersUnknownRoutesAsRouteNotFound(): void
    {
        $response = $this->render(new NotFoundHttpException('The route api/v1/unknown could not be found.'));

        $this->assertError($response, 404, 'route_not_found');
    }

    #[Test]
    public function keepsTheHeadersOfHttpExceptions(): void
    {
        $methodNotAllowed = $this->render(new MethodNotAllowedHttpException(['GET', 'PUT']));
        $this->assertError($methodNotAllowed, 405, 'method_not_allowed');
        $this->assertSame('GET, PUT', $methodNotAllowed->headers->get('Allow'));

        $tooManyRequests = $this->render(new TooManyRequestsHttpException(30));
        $this->assertError($tooManyRequests, 429, 'too_many_requests');
        $this->assertSame('30', $tooManyRequests->headers->get('Retry-After'));
    }

    #[Test]
    public function rendersServerSideHttpErrorsWithTheStandardReasonPhrase(): void
    {
        $response = $this->render(new ServiceUnavailableHttpException(null, 'Internal maintenance detail'));

        $this->assertError($response, 503, 'service_unavailable');
        $this->assertSame('Service Unavailable', $this->field($response, 'message'));
    }

    #[Test]
    public function rendersDatabaseUniqueViolationsAsConflict(): void
    {
        $exception = new UniqueConstraintViolationException(
            'mysql',
            'insert into contract_devices ...',
            [],
            new PDOException('Duplicate entry'),
        );

        $response = $this->render($exception);

        $this->assertError($response, 409, 'conflict');
        $this->assertStringNotContainsString('Duplicate entry', (string) $response->getContent());
    }

    #[Test]
    public function hidesTheDetailsOfUnexpectedErrors(): void
    {
        $response = $this->render(new RuntimeException('Sensitive internal detail'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame([
            'message' => 'An unexpected error occurred.',
            'code' => 'internal_server_error',
            'request_id' => 'test-request-id',
        ], $response->getData(true));
    }

    #[Test]
    public function addsDebugDetailsOfUnexpectedErrorsInDebugMode(): void
    {
        $response = $this->render(new RuntimeException('Sensitive internal detail'), debug: true);

        $this->assertError($response, 500, 'internal_server_error');
        $this->assertSame(RuntimeException::class, $this->field($response, 'debug.exception'));
        $this->assertSame('Sensitive internal detail', $this->field($response, 'debug.message'));
        $this->assertNotEmpty($this->field($response, 'debug.trace'));
    }

    #[Test]
    public function leavesExceptionsWithTheirOwnResponseToTheFramework(): void
    {
        $exception = new HttpResponseException(new Response('Custom response'));

        $this->assertNull($this->renderer(debug: false)->render($exception));
    }

    private function assertError(JsonResponse $response, int $status, string $code): void
    {
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame($code, $this->field($response, 'code'));
        $this->assertSame('test-request-id', $this->field($response, 'request_id'));
    }

    private function field(JsonResponse $response, string $key): mixed
    {
        return data_get($response->getData(true), $key);
    }

    private function render(Throwable $exception, bool $debug = false): JsonResponse
    {
        $response = $this->renderer($debug)->render($exception);

        $this->assertNotNull($response);

        return $response;
    }

    private function renderer(bool $debug): ApiExceptionRenderer
    {
        $context = $this->app->make(Context::class);
        $context->add(AssignRequestId::CONTEXT_KEY, 'test-request-id');

        return new ApiExceptionRenderer($context, $debug);
    }
}
