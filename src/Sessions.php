<?php

declare(strict_types=1);

namespace Torii\Backend;

use Torii\Backend\Generated\Api\ServerSessionsApi;
use Torii\Backend\Generated\ApiException as GeneratedApiException;
use Torii\Backend\Generated\Model\UserSessionResponse;

/**
 * REST client for `/api/server/v1/users/{userId}/sessions`.
 */
final class Sessions
{
    public function __construct(private readonly ServerSessionsApi $api)
    {
    }

    /**
     * @return UserSessionResponse[]
     */
    public function listForUser(string $userId): array
    {
        try {
            return $this->api->listSessions($userId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    public function revokeAllForUser(string $userId): void
    {
        try {
            $this->api->revokeAllSessions($userId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }

    public function revoke(string $userId, string $sessionId): void
    {
        try {
            $this->api->revokeSession($userId, $sessionId);
        } catch (GeneratedApiException $e) {
            throw _torii_wrap_api_exception($e);
        }
    }
}
