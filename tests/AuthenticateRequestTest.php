<?php

declare(strict_types=1);

namespace Torii\Backend\Tests;

use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Torii\Backend\AuthException;
use function Torii\Backend\_clear_jwks_cache_for_tests;
use function Torii\Backend\authenticate_request;

final class AuthenticateRequestTest extends TestCase
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
        _clear_jwks_cache_for_tests();
    }

    private function token(): string
    {
        $now = time();
        return JWT::encode(
            [
                'sub' => 'user-1',
                'pid' => 'env-1',
                'iss' => self::$server->issuerUrl(),
                'iat' => $now,
                'exp' => $now + 600,
            ],
            self::$server->privateKeyPem,
            'ES256',
            self::$server->kid,
        );
    }

    #[Test]
    public function accepts_array_headers(): void
    {
        $auth = authenticate_request(
            request: ['Authorization' => 'Bearer ' . $this->token()],
            issuer: self::$server->issuerUrl(),
        );
        $this->assertSame('user-1', $auth->userId);
        $this->assertSame('env-1', $auth->environmentId);
    }

    #[Test]
    public function accepts_psr7_request(): void
    {
        $request = (new ServerRequest('GET', self::$server->issuerUrl() . '/me'))
            ->withHeader('Authorization', 'Bearer ' . $this->token());

        $auth = authenticate_request($request, self::$server->issuerUrl());
        $this->assertSame('user-1', $auth->userId);
    }

    #[Test]
    public function header_lookup_is_case_insensitive(): void
    {
        $auth = authenticate_request(
            request: ['authorization' => 'Bearer ' . $this->token()],
            issuer: self::$server->issuerUrl(),
        );
        $this->assertSame('user-1', $auth->userId);
    }

    #[Test]
    public function rejects_missing_header(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/Missing authorization header/');
        authenticate_request(request: [], issuer: self::$server->issuerUrl());
    }

    #[Test]
    public function rejects_non_bearer_scheme(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches("/'Bearer <token>' form/");
        authenticate_request(
            request: ['Authorization' => 'Basic abc123'],
            issuer: self::$server->issuerUrl(),
        );
    }
}
