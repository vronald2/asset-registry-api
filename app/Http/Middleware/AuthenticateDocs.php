<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the API documentation with HTTP Basic Auth (credentials from config/api.php).
 *
 * Fails closed: while no credentials are configured, nobody can access the documentation.
 */
final readonly class AuthenticateDocs
{
    public function __construct(
        #[Config('api.docs.username')] private string $username,
        #[Config('api.docs.password'), SensitiveParameter] private string $password,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hasValidCredentials($request)) {
            return new HttpResponse('Authentication required.', Response::HTTP_UNAUTHORIZED, [
                'WWW-Authenticate' => 'Basic realm="API documentation", charset="UTF-8"',
            ]);
        }

        return $next($request);
    }

    private function hasValidCredentials(Request $request): bool
    {
        if ($this->username === '' || $this->password === '') {
            return false;
        }

        // Constant-time comparison, so the credentials cannot be guessed from response times.
        return hash_equals($this->username, (string) $request->getUser())
            && hash_equals($this->password, (string) $request->getPassword());
    }
}
