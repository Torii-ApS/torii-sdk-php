<?php

declare(strict_types=1);

namespace Torii\Backend;

use Firebase\JWT\CachedKeySet;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;
use Torii\Backend\Internal\ArrayCachePool;

/**
 * Per-issuer CachedKeySet cache.
 *
 * php-jwt's {@see CachedKeySet} fetches JWKS lazily, caches keys with TTL, and
 * rotates keys on `kid` miss. We keep one instance per issuer for the
 * lifetime of the process.
 *
 * @var array<string, CachedKeySet>
 */
$GLOBALS['__torii_jwks_keysets'] ??= [];

/** Process-wide PSR-6 fallback shared by all auto-created CachedKeySets. */
$GLOBALS['__torii_default_cache_pool'] ??= null;

function _torii_default_cache_pool(): CacheItemPoolInterface
{
    if ($GLOBALS['__torii_default_cache_pool'] === null) {
        $GLOBALS['__torii_default_cache_pool'] = new ArrayCachePool();
    }
    return $GLOBALS['__torii_default_cache_pool'];
}

/**
 * Build (or fetch from cache) a JWKS key set for the given issuer.
 *
 * Hard-coded path: torii's JWKS endpoint lives at
 * `/_torii/.well-known/jwks.json` for every tenant. Stable contract documented
 * in our OIDC discovery doc; we skip the discovery round-trip on the cold path.
 *
 * @internal
 */
function _torii_jwks_for_issuer(string $issuer, ?CacheItemPoolInterface $cache = null): CachedKeySet
{
    $normalized = rtrim($issuer, '/');
    $cacheKey = $cache === null ? "default::$normalized" : spl_object_hash($cache) . "::$normalized";

    if (isset($GLOBALS['__torii_jwks_keysets'][$cacheKey])) {
        return $GLOBALS['__torii_jwks_keysets'][$cacheKey];
    }

    $httpClient = new GuzzleClient(['timeout' => 10.0]);
    $httpFactory = new HttpFactory();
    $pool = $cache ?? _torii_default_cache_pool();

    $keySet = new CachedKeySet(
        $normalized . '/_torii/.well-known/jwks.json',
        $httpClient,
        $httpFactory,
        $pool,
        // expiresAfter: 300s. CachedKeySet falls back to re-fetch on kid miss.
        300,
        // rateLimit: cap JWKS fetches to 10/min to absorb burst failures.
        true,
        'ES256',
    );

    $GLOBALS['__torii_jwks_keysets'][$cacheKey] = $keySet;
    return $keySet;
}

/**
 * Verify a torii-issued JWT against the issuer's JWKS.
 *
 * Networkless after the first call per issuer: the JWKS is fetched once,
 * cached, and rotated automatically when an unseen `kid` shows up.
 *
 * @param string $token Compact JWS as received from the customer's frontend.
 * @param string $issuer Expected issuer URL (per-tenant), e.g.
 *                      `https://acme.torii.so` or `https://auth.acme.com`.
 *                      Required — strict iss validation is the point.
 * @param string[]|null $audience Optional `aud` claim(s) to enforce. torii
 *                              doesn't set `aud` today; leave null to skip.
 * @param int $leeway Clock-skew tolerance in seconds for `exp`/`nbf`. Default 30.
 * @param CacheItemPoolInterface|null $cache Optional PSR-6 cache for JWKS
 *                                          storage. Defaults to a process-local
 *                                          {@see ArrayCachePool}.
 *
 * @throws AuthException If signature, issuer, expiry, or required claims fail.
 */
function verify_token(
    string $token,
    string $issuer,
    ?array $audience = null,
    int $leeway = 30,
    ?CacheItemPoolInterface $cache = null,
): Auth {
    if ($token === '') {
        throw new AuthException('verify_token: token must be a non-empty string');
    }
    if ($issuer === '') {
        throw new AuthException('verify_token: issuer is required');
    }

    $keySet = _torii_jwks_for_issuer($issuer, $cache);

    // JWT::$leeway is a static — preserve and restore to avoid cross-call bleed.
    $previousLeeway = JWT::$leeway;
    JWT::$leeway = $leeway;
    try {
        try {
            $decoded = JWT::decode($token, $keySet);
        } catch (ExpiredException | SignatureInvalidException $e) {
            throw new AuthException('JWT verification failed: ' . $e->getMessage(), $e);
        } catch (Throwable $e) {
            throw new AuthException('JWT verification failed: ' . $e->getMessage(), $e);
        }
    } finally {
        JWT::$leeway = $previousLeeway;
    }

    /** @var array<string, mixed> $payload */
    $payload = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

    foreach (['sub', 'iat', 'exp', 'iss'] as $required) {
        if (!array_key_exists($required, $payload)) {
            throw new AuthException("JWT is missing required claim: $required");
        }
    }

    $expectedIssuer = rtrim($issuer, '/');
    $actualIssuer = is_string($payload['iss']) ? rtrim($payload['iss'], '/') : '';
    if ($actualIssuer !== $expectedIssuer) {
        throw new AuthException(
            "JWT issuer mismatch: expected $expectedIssuer, got " . (is_string($payload['iss']) ? $payload['iss'] : '<non-string>')
        );
    }

    if ($audience !== null && $audience !== []) {
        $aud = $payload['aud'] ?? null;
        $audList = is_array($aud) ? $aud : [$aud];
        $matched = false;
        foreach ($audience as $expected) {
            if (in_array($expected, $audList, true)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            throw new AuthException('JWT audience mismatch');
        }
    }

    $userId = $payload['sub'];
    $environmentId = $payload['pid'] ?? null;
    if (!is_string($userId) || !is_string($environmentId)) {
        throw new AuthException('JWT is missing required string claims (sub, pid)');
    }

    $locale = $payload['locale'] ?? null;

    return new Auth(
        userId: $userId,
        environmentId: $environmentId,
        issuer: $actualIssuer,
        emailVerified: ($payload['email_verified'] ?? false) === true,
        profileComplete: ($payload['profile_complete'] ?? true) !== false,
        impersonating: ($payload['impersonating'] ?? false) === true,
        locale: is_string($locale) ? $locale : null,
        raw: $payload,
    );
}

/**
 * Test-only: clear cached JWKS key sets. Production code should never call
 * this — {@see CachedKeySet} handles rotation internally via `kid`.
 *
 * @internal
 */
function _clear_jwks_cache_for_tests(): void
{
    $GLOBALS['__torii_jwks_keysets'] = [];
    $GLOBALS['__torii_default_cache_pool'] = null;
}
