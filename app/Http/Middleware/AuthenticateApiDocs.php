<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * HTTP Basic Auth of the API documentation. It fails closed: without configured credentials nobody gets in.
 */
final readonly class AuthenticateApiDocs
{
    public function __construct(
        #[Config('api-docs.username')]
        private string $username,
        #[Config('api-docs.password')]
        private string $password,
        #[Config('api-docs.realm')]
        private string $realm,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isAuthenticated($request)) {
            throw new UnauthorizedHttpException(
                sprintf('Basic realm="%s", charset="UTF-8"', $this->realm),
                'Valid credentials are required to access the API documentation.',
            );
        }

        return $next($request);
    }

    private function isAuthenticated(Request $request): bool
    {
        if ($this->username === '' || $this->password === '') {
            return false;
        }

        // Both comparisons always run in constant time, so the response does not reveal which part was wrong.
        $usernameMatches = hash_equals($this->username, (string) $request->getUser());
        $passwordMatches = hash_equals($this->password, (string) $request->getPassword());

        return $usernameMatches && $passwordMatches;
    }
}
