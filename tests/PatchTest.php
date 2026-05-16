<?php

declare(strict_types=1);

namespace Torii\Backend\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Torii\Backend\Patch;

final class PatchTest extends TestCase
{
    #[Test]
    public function set_wraps_a_value(): void
    {
        $p = Patch::set('Ada');
        $this->assertSame('Ada', $p->value);
    }

    #[Test]
    public function set_accepts_null_value_for_clear_semantics(): void
    {
        // Patch::set(null) is the canonical "clear" — it emits the JSON
        // key with an explicit null on the wire, which the server
        // interprets as "clear this field".
        $p = Patch::set(null);
        $this->assertNull($p->value);
    }
}
