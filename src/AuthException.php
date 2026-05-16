<?php

declare(strict_types=1);

namespace Torii\Backend;

use RuntimeException;
use Throwable;

/**
 * Thrown when a JWT cannot be verified, a Bearer header is missing/malformed,
 * or when calling a not-yet-shipped helper (e.g. {@see verify_webhook}).
 *
 * Distinct from {@see ApiException} (which represents a non-2xx response from
 * the torii backend REST API). Catch this when you want to map auth failures
 * to a 401.
 */
final class AuthException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
