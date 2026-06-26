<?php

declare(strict_types=1);

namespace Torii\Backend;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\MessageInterface;

/**
 * Extract a Bearer token from request headers and verify it.
 *
 * Accepts either a plain header array (`['authorization' => 'Bearer ...']`)
 * or any PSR-7 message (Slim/Laminas/Symfony PSR-7 bridge/Guzzle response).
 * Header lookup is case-insensitive.
 *
 * @param array<string, string|string[]>|MessageInterface $request
 * @param string $issuer Expected issuer URL (per-tenant). Required.
 * @param string[]|null $audience Optional `aud` claim(s) to enforce.
 * @param int $leeway Clock-skew tolerance in seconds for `exp`/`nbf`.
 * @param string $header Header name to read. Defaults to `authorization`.
 * @param CacheItemPoolInterface|null $cache Optional PSR-6 cache for JWKS.
 *
 * @throws AuthException If header missing, malformed, or token invalid.
 */
function authenticate_request(
    array|MessageInterface $request,
    string $issuer,
    ?array $audience = null,
    int $leeway = 30,
    string $header = 'authorization',
    ?CacheItemPoolInterface $cache = null,
): Auth {
    $raw = _torii_read_header($request, $header);
    if ($raw === null || $raw === '') {
        throw new AuthException("Missing $header header");
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', $raw, $matches)) {
        throw new AuthException("$header header is not in 'Bearer <token>' form");
    }

    return verify_token(
        token: trim($matches[1]),
        issuer: $issuer,
        audience: $audience,
        leeway: $leeway,
        cache: $cache,
    );
}

/**
 * @param array<string, string|string[]>|MessageInterface $request
 * @internal
 */
function _torii_read_header(array|MessageInterface $request, string $name): ?string
{
    if ($request instanceof MessageInterface) {
        $value = $request->getHeaderLine($name);
        return $value === '' ? null : $value;
    }

    $target = strtolower($name);
    foreach ($request as $key => $value) {
        if (strtolower((string) $key) !== $target) {
            continue;
        }
        if (is_array($value)) {
            return $value[0] ?? null;
        }
        return is_string($value) ? $value : null;
    }
    return null;
}
