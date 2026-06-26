<?php

declare(strict_types=1);

namespace Torii\Backend;

/**
 * Subset of fields the backend SDK exposes from a verified torii JWT.
 *
 * For full claim access (custom claims, audience, etc.) read {@see $raw}.
 */
final readonly class Auth
{
    public function __construct(
        /** End-user ID (JWT `sub`). */
        public string $userId,
        /** Environment ID this token was issued in (JWT `pid`). */
        public string $environmentId,
        /** Issuer (JWT `iss`) — the canonical FAPI URL for this environment. */
        public string $issuer,
        /** True if the end-user has verified at least one of their email addresses. */
        public bool $emailVerified,
        /** True if all environment-required profile fields are filled. */
        public bool $profileComplete,
        /** True if the token is being used for admin impersonation. */
        public bool $impersonating,
        /** End-user's preferred locale, when set on the profile. */
        public ?string $locale,
        /**
         * Raw decoded JWT payload — escape hatch for custom claims, audience
         * checks, etc.
         *
         * @var array<string, mixed>
         */
        public array $raw,
    ) {
    }
}
