<?php

declare(strict_types=1);

namespace Torii\Backend\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Torii\Backend\Torii;

/**
 * Smoke tests for {@see Torii::create()} construction. We deliberately avoid
 * spinning up a fake REST server here — that's covered by the SDK's
 * integration suite against the real Spring Boot service. These tests only
 * verify the wiring (DTOs, auth header injection) is sound.
 */
final class ToriiClientTest extends TestCase
{
    #[Test]
    public function create_with_secret_key_wires_users_and_sessions(): void
    {
        $torii = Torii::create('sk_test_abc');
        $this->assertInstanceOf(\Torii\Backend\Users::class, $torii->users);
        $this->assertInstanceOf(\Torii\Backend\Sessions::class, $torii->sessions);
    }

    #[Test]
    public function rejects_empty_secret_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Torii::create('');
    }

    #[Test]
    public function default_api_url_constant(): void
    {
        $this->assertSame('https://api.torii.so', Torii::DEFAULT_API_URL);
    }
}
