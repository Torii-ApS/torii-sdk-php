<?php

declare(strict_types=1);

namespace Torii\Backend;

/**
 * Tri-state wrapper for PATCH body fields. Mirrors the server-side
 * PatchValue<T> exactly: a "present" state carrying a value (which may
 * be null to clear the field), and the absence of the entry in the
 * patch array, which leaves the server-side field alone.
 *
 * - {@see Patch::set()} with a non-null value → server updates the field
 * - {@see Patch::set()} with null              → server clears the field (sends JSON `null`)
 * - Omit the entry from the patch array       → server leaves the field unchanged
 */
final class Patch
{
    private function __construct(
        public readonly mixed $value,
    ) {
    }

    public static function set(mixed $value): self
    {
        return new self($value);
    }
}
