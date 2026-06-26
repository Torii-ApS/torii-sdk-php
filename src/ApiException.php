<?php

declare(strict_types=1);

namespace Torii\Backend;

use RuntimeException;
use Throwable;

/**
 * Thrown when the torii backend REST API returns a non-2xx response, or when
 * the generated client throws. Wraps the generated {@see Generated\ApiException}
 * with a hand-curated surface (status code + parsed body) so callers don't
 * import the generated namespace.
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly mixed $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * If the response body parses as JSON and contains a `code` field, return it.
     * Mirrors {@see \Torii\Backend\Generated\ApiException} fields.
     */
    public function code(): ?string
    {
        if (is_array($this->body) && isset($this->body['code']) && is_string($this->body['code'])) {
            return $this->body['code'];
        }
        return null;
    }
}
