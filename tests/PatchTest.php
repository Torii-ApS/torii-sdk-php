<?php

declare(strict_types=1);

namespace Torii\Backend\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Torii\Backend\Patch;

final class PatchTest extends TestCase
{
    #[Test]
    public function set_returns_set_state_with_value(): void
    {
        $p = Patch::set('Ada');
        $this->assertSame(Patch::STATE_SET, $p->state);
        $this->assertSame('Ada', $p->value);
    }

    #[Test]
    public function set_accepts_null_value(): void
    {
        // Patch::set(null) is semantically odd but legal; the wrapper doesn't
        // judge — it just records "set this field to null". (Most callers will
        // want Patch::clear() instead, which is exactly the same wire effect.)
        $p = Patch::set(null);
        $this->assertSame(Patch::STATE_SET, $p->state);
        $this->assertNull($p->value);
    }

    #[Test]
    public function clear_returns_clear_state_with_null_value(): void
    {
        $p = Patch::clear();
        $this->assertSame(Patch::STATE_CLEAR, $p->state);
        $this->assertNull($p->value);
    }

    #[Test]
    public function state_constants_are_stable_strings(): void
    {
        // The constant values aren't part of the public API per se, but
        // hard-coding them in tests catches accidental rename regressions
        // that would silently flip the wire behaviour.
        $this->assertSame('set', Patch::STATE_SET);
        $this->assertSame('clear', Patch::STATE_CLEAR);
    }
}
