<?php

declare(strict_types=1);

namespace Torii\Backend;

/**
 * Verify an outbound torii webhook signature.
 *
 * NOTE: torii's outbound webhook subsystem hasn't shipped yet (tracked in
 * GitHub issue #424 Phase 0.5). This stub keeps the SDK surface stable so
 * adopting it later won't be a breaking change for callers.
 *
 * @param string $secret Webhook signing secret from the torii dashboard.
 * @param array<string, string|string[]> $headers Incoming request headers.
 * @param string $payload Raw request body.
 *
 * @return array<string, mixed> Parsed event body once implemented.
 *
 * @throws AuthException Always, until #424 Phase 0.5 ships.
 */
function verify_webhook(string $secret, array $headers, string $payload): array
{
    throw new AuthException(
        "verify_webhook: torii's outbound webhook subsystem has not shipped yet — see #424 Phase 0.5"
    );
}
