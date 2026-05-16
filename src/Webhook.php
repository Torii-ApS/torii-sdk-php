<?php

declare(strict_types=1);

namespace Torii\Backend;

/**
 * Verify an outbound torii webhook signature.
 *
 * NOTE: torii's outbound webhook subsystem is not yet available. This stub
 * reserves the SDK surface so adopting it later won't be a breaking change
 * for callers.
 *
 * @param string $secret Webhook signing secret from the torii dashboard.
 * @param array<string, string|string[]> $headers Incoming request headers.
 * @param string $payload Raw request body.
 *
 * @return array<string, mixed> Parsed event body once implemented.
 *
 * @throws AuthException Always, until the webhook subsystem ships.
 */
function verify_webhook(string $secret, array $headers, string $payload): array
{
    throw new AuthException(
        "verify_webhook: torii's outbound webhook subsystem is not yet available."
    );
}
