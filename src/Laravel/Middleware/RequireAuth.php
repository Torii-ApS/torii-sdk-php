<?php

declare(strict_types=1);

namespace Torii\Backend\Laravel\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Torii\Backend\AuthException;
use function Torii\Backend\authenticate_request;

/**
 * Verifies the incoming `Authorization: Bearer <jwt>` header against torii's
 * JWKS for the configured issuer. On success, sets `$request->torii_auth`
 * (an instance of {@see \Torii\Backend\Auth}). On failure, returns 401 JSON.
 *
 *     // routes/api.php
 *     Route::middleware(\Torii\Backend\Laravel\Middleware\RequireAuth::class)
 *         ->get('/me', fn (Request $r) => $r->torii_auth);
 */
class RequireAuth
{
    public function __construct(private readonly ConfigRepository $config)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $issuer = $this->config->get('torii.issuer');
        if (!is_string($issuer) || $issuer === '') {
            throw new \RuntimeException(
                'torii.issuer is not configured. Set TORII_ISSUER in your .env (e.g. https://acme.torii.so).'
            );
        }

        try {
            $auth = authenticate_request(
                request: $this->collectHeaders($request),
                issuer: $issuer,
            );
        } catch (AuthException $e) {
            return new JsonResponse(
                data: [
                    'error' => [
                        'code' => 'authentication_failed',
                        'message' => $e->getMessage(),
                    ],
                ],
                status: 401,
            );
        }

        // Laravel's Request inherits Symfony's __set / dynamic-attribute support.
        $request->torii_auth = $auth;
        // Also expose via the canonical attributes bag for adapters that
        // prefer ServerRequest-style access.
        $request->attributes->set('torii_auth', $auth);

        return $next($request);
    }

    /**
     * Laravel's `Request::header()` works one-at-a-time; pull all into an
     * array so we don't bypass the framework's normalisation.
     *
     * @return array<string, string|string[]>
     */
    private function collectHeaders(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = $values;
        }
        return $headers;
    }
}
