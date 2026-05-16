<?php

declare(strict_types=1);

namespace Torii\Backend\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Torii\Backend\AuthException;
use function Torii\Backend\verify_webhook;

final class WebhookTest extends TestCase
{
    #[Test]
    public function stub_throws_until_implemented(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessageMatches('/not yet available/');
        verify_webhook(secret: 'whsec_xxx', headers: [], payload: '{}');
    }
}
