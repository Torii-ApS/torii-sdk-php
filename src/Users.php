<?php

declare(strict_types=1);

namespace Torii\Backend;

use DateTimeInterface;
use Torii\Backend\Generated\Api\ServerUsersApi;
use Torii\Backend\Generated\ApiException as GeneratedApiException;
use Torii\Backend\Generated\Model\CreateUserRequest;
use Torii\Backend\Generated\Model\CursorPageResponseUserResponse;
use Torii\Backend\Generated\Model\ServerUserSearchRequest;
use Torii\Backend\Generated\Model\UpdateUserRequest;
use Torii\Backend\Generated\Model\UserResponse;

/**
 * REST client for `/api/server/v1/users`. Thin wrapper around the generated
 * {@see ServerUsersApi} — keeps method names snake_case-ish PHP-style and
 * hides the generated request DTOs from callers.
 */
final class Users
{
    public function __construct(private readonly ServerUsersApi $api)
    {
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
    ): CursorPageResponseUserResponse {
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

    public function get(string $userId): UserResponse
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
     * @param array<string, mixed> $data Maps to {@see CreateUserRequest} fields:
     *                                   email, password, name, phone, address, dateOfBirth.
     */
    public function create(array $data): UserResponse
    {
        try {
            return $this->api->createUser(new CreateUserRequest(_torii_snake_keys($data)));
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    /**
     * Update a user (PATCH).
     *
     * @param array<string, mixed> $data Maps to {@see UpdateUserRequest} fields.
     */
    public function update(string $userId, array $data): UserResponse
    {
        try {
            return $this->api->updateUser($userId, new UpdateUserRequest(_torii_snake_keys($data)));
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    public function delete(string $userId): void
    {
        try {
            $this->api->deleteUser($userId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    public function ban(string $userId): UserResponse
    {
        try {
            return $this->api->banUser($userId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    public function unban(string $userId): UserResponse
    {
        try {
            return $this->api->unbanUser($userId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }
}

/**
 * The generated DTOs expect snake_case property names but real-world callers
 * naturally pass camelCase (matches the JSON wire). Convert at the boundary.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 *
 * @internal
 */
function _torii_snake_keys(array $data): array
{
    $out = [];
    foreach ($data as $key => $value) {
        $snake = strtolower((string) preg_replace('/([A-Z])/', '_$1', (string) $key));
        $out[ltrim($snake, '_')] = $value;
    }
    return $out;
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
