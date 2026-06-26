<?php

declare(strict_types=1);

namespace Torii\Backend;

use DateTimeInterface;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Torii\Backend\Generated\Api\ServerUsersApi;
use Torii\Backend\Generated\ApiException as GeneratedApiException;
use Torii\Backend\Generated\Model\CursorPageResponseServerUserResponse;
use Torii\Backend\Generated\Model\ServerUserResponse;
use Torii\Backend\Generated\Model\ServerUserSearchRequest;
use Torii\Backend\Generated\ObjectSerializer;

/**
 * REST client for `/api/server/v1/users`. Thin wrapper around the generated
 * {@see ServerUsersApi} — keeps method names snake_case-ish PHP-style and
 * hides the generated request DTOs from callers.
 */
final class Users
{
    public function __construct(
        private readonly ServerUsersApi $api,
        private readonly ClientInterface $httpClient,
        private readonly string $host,
    ) {
    }

    /**
     * List users with optional filters.
     *
     * @param string[]|null $statuses User status filter.
     */
    public function list(
        ?int $limit = null,
        ?string $cursor = null,
        ?string $name = null,
        ?string $email = null,
        ?array $statuses = null,
        DateTimeInterface|string|null $createdAfter = null,
        DateTimeInterface|string|null $createdBefore = null,
    ): CursorPageResponseServerUserResponse {
        $body = new ServerUserSearchRequest([
            'name' => $name,
            'email' => $email,
            'statuses' => $statuses,
            'created_after' => $createdAfter instanceof DateTimeInterface
                ? $createdAfter->format(DateTimeInterface::RFC3339)
                : $createdAfter,
            'created_before' => $createdBefore instanceof DateTimeInterface
                ? $createdBefore->format(DateTimeInterface::RFC3339)
                : $createdBefore,
        ]);
        try {
            return $this->api->searchUsers($limit ?? 20, $cursor, $body);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    public function get(string $userId): ServerUserResponse
    {
        try {
            return $this->api->getUser($userId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    /**
     * Create a user.
     *
     * @param array<string, mixed> $data Create fields, camelCase to match the
     *        wire: `email`, `password`, `firstName`, `lastName`, and the three
     *        metadata bags `publicMetadata` / `privateMetadata` /
     *        `unsafeMetadata`. Each metadata bag is required by the API and
     *        defaults to an empty object (`{}`) when omitted — a brand-new user
     *        has no metadata to clobber.
     */
    public function create(array $data): ServerUserResponse
    {
        // The three metadata bags are required objects. Default each to an
        // empty object when the caller omits it. PHP's `[]` would JSON-encode
        // as `[]` (array), which the server rejects, so use stdClass for `{}`.
        foreach (['publicMetadata', 'privateMetadata', 'unsafeMetadata'] as $bag) {
            if (!array_key_exists($bag, $data) || $data[$bag] === null || $data[$bag] === []) {
                $data[$bag] = new \stdClass();
            }
        }
        return $this->sendJson('POST', '/api/server/v1/users', $data);
    }

    /**
     * Update a user (PATCH) with tri-state field semantics.
     *
     * PHP arrays can't natively distinguish "leave field alone" from
     * "set field to null", so each value must be a {@see Patch} instance:
     *
     *     $torii->users->update($id, [
     *         'firstName'      => Patch::set('Ada'),
     *         'lastName'       => Patch::set(null),               // clear
     *         'unsafeMetadata' => Patch::set(['tier' => 'pro']),  // replace bag
     *     ]);
     *
     * Omit a field from `$patches` entirely to leave the server value alone.
     * Patchable fields: `firstName`, `lastName`, `locale`, `unsafeMetadata`.
     *
     * @param array<string, Patch> $patches Field name → {@see Patch} instance.
     */
    public function update(string $userId, array $patches): ServerUserResponse
    {
        $body = [];
        foreach ($patches as $field => $patch) {
            if (!($patch instanceof Patch)) {
                throw new \InvalidArgumentException(
                    "Users::update: field '$field' must be a "
                    . Patch::class . " instance; got " . get_debug_type($patch)
                );
            }
            // Patch::set(value) emits the key; null value → JSON null (clear).
            $body[$field] = $patch->value;
        }

        return $this->sendJson('PATCH', '/api/server/v1/users/' . rawurlencode($userId), $body);
    }

    /**
     * Send a JSON body to the users API and deserialize the response into a
     * {@see ServerUserResponse}. Bypasses the generated request DTOs so the
     * body we build (tri-state PATCH keys, `{}` metadata bags) survives
     * serialization untouched.
     *
     * @param array<string, mixed> $bodyData
     */
    private function sendJson(string $method, string $path, array $bodyData): ServerUserResponse
    {
        try {
            // Cast to object so an empty body serializes as `{}`, not `[]`.
            $json = json_encode((object) $bodyData, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                "Users: failed to JSON-encode $method body: " . $e->getMessage(),
                previous: $e,
            );
        }

        $request = new Psr7Request(
            $method,
            $this->host . $path,
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $json,
        );

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new ApiException(
                message: 'torii API request failed: ' . $e->getMessage(),
                statusCode: 0,
                body: null,
                previous: $e,
            );
        }

        $status = $response->getStatusCode();
        $responseBody = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            $parsed = null;
            if ($responseBody !== '') {
                try {
                    $parsed = json_decode($responseBody, true, flags: JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $parsed = $responseBody;
                }
            }
            throw new ApiException(
                message: "torii API error ($status)",
                statusCode: $status,
                body: $parsed,
            );
        }

        $decoded = json_decode($responseBody);
        return ObjectSerializer::deserialize($decoded, ServerUserResponse::class, []);
    }

    public function delete(string $userId): void
    {
        try {
            $this->api->deleteUser($userId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    public function ban(string $userId): ServerUserResponse
    {
        try {
            return $this->api->banUser($userId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    public function unban(string $userId): ServerUserResponse
    {
        try {
            return $this->api->unbanUser($userId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }
}

/**
 * @internal
 */
function _torii_wrap_api_exception(GeneratedApiException $e): ApiException
{
    $body = $e->getResponseBody();
    $parsed = null;
    if (is_string($body) && $body !== '') {
        try {
            $parsed = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $parsed = $body;
        }
    } elseif (is_object($body)) {
        $parsed = json_decode(json_encode($body), true);
    } else {
        $parsed = $body;
    }
    return new ApiException(
        message: $e->getMessage() !== '' ? $e->getMessage() : "torii API error ({$e->getCode()})",
        statusCode: (int) $e->getCode(),
        body: $parsed,
        previous: $e,
    );
}
