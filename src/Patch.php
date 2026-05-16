<?php

declare(strict_types=1);

namespace Torii\Backend;

/**
 * Tri-state wrapper for PATCH body fields.
 *
 * PHP arrays don't natively distinguish "key absent" from "key present with
 * null". This wrapper makes the three PATCH states explicit:
 *
 * - {@see Patch::set()}   → server updates field to the given value.
 * - {@see Patch::clear()} → server clears the field (sends JSON `null`).
 * - Omit the entry from the patch array entirely → server leaves field alone.
 */
final class Patch
{
    public const STATE_SET = 'set';
    public const STATE_CLEAR = 'clear';

    private function __construct(
        public readonly string $state,
        public readonly mixed $value = null,
    ) {
    }

    public static function set(mixed $value): self
    {
        return new self(self::STATE_SET, $value);
    }

    public static function clear(): self
    {
        return new self(self::STATE_CLEAR);
    }
}
