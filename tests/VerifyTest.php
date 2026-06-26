<?php

declare(strict_types=1);

namespace Torii\Backend\Tests;

use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Torii\Backend\AuthException;
use function Torii\Backend\_clear_jwks_cache_for_tests;
use function Torii\Backend\verify_token;

final class VerifyTest extends TestCase
{
    private static JwksTestServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = JwksTestServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    protected function setUp(): void
    {
        // Each test starts with a clean JWKS cache so we always exercise the
        // real fetch path against the in-process server.
        _clear_jwks_cache_for_tests();
    }

    /** Helper: sign a JWT with a payload, using the test server's key. */
    private function sign(array $payload, ?string $key = null, ?string $kid = null): string
    {
        return JWT::encode(
            $payload,
            $key ?? self::$server->privateKeyPem,
            'ES256',
            $kid ?? self::$server->kid,
        );
    }

    private function basePayload(array $overrides = []): array
    {
        $now = time();
        return array_replace([
            'sub' => '11111111-1111-1111-1111-111111111111',
            'pid' => '22222222-2222-2222-2222-222222222222',
            'iss' => self::$server->issuerUrl(),
            'iat' => $now,
            'exp' => $now + 600,
            'email_verified' => true,
            'profile_complete' => true,
            'impersonating' => false,
            'locale' => 'en',
        ], $overrides);
    }

    #[Test]
    public function verifies_and_extracts_claims(): void
    {
        $token = $this->sign($this->basePayload());

        $auth = verify_token($token, self::$server->issuerUrl());

        $this->assertSame('11111111-1111-1111-1111-111111111111', $auth->userId);
        $this->assertSame('22222222-2222-2222-2222-222222222222', $auth->environmentId);
        $this->assertSame(self::$server->issuerUrl(), $auth->issuer);
        $this->assertTrue($auth->emailVerified);
        $this->assertTrue($auth->profileComplete);
        $this->assertFalse($auth->impersonating);
        $this->assertSame('en', $auth->locale);
        $this->assertIsArray($auth->raw);
        $this->assertSame(self::$server->issuerUrl(), $auth->raw['iss']);
    }

    #[Test]
    public function rejects_token_signed_with_wrong_key(): void
    {
        $wrongPkey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        openssl_pkey_export($wrongPkey, $wrongPem);

        $token = $this->sign($this->basePayload(), $wrongPem);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/JWT verification failed/i');
        verify_token($token, self::$server->issuerUrl());
    }

    #[Test]
    public function rejects_wrong_issuer(): void
    {
        $token = $this->sign($this->basePayload(['iss' => 'https://attacker.example.com']));

        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/issuer mismatch/i');
        verify_token($token, self::$server->issuerUrl());
    }

    #[Test]
    public function rejects_missing_required_claim(): void
    {
        $payload = $this->basePayload();
        unset($payload['sub']);
        $token = $this->sign($payload);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/missing required claim/i');
        verify_token($token, self::$server->issuerUrl());
    }

    #[Test]
    public function rejects_expired_token(): void
    {
        $token = $this->sign($this->basePayload([
            'iat' => time() - 7200,
            'exp' => time() - 3600,
        ]));

        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/expired/i');
        verify_token($token, self::$server->issuerUrl(), leeway: 5);
    }

    #[Test]
    public function rejects_empty_token(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/non-empty string/');
        verify_token('', self::$server->issuerUrl());
    }
}
