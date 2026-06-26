<?php

declare(strict_types=1);

namespace Torii\Backend;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;
use Torii\Backend\Generated\Api\ServerSessionsApi;
use Torii\Backend\Generated\Api\ServerUsersApi;
use Torii\Backend\Generated\Configuration;

/**
 * Entry point for the torii backend REST API.
 *
 * Construct via {@see Torii::create()}:
 *
 *     $torii = Torii::create($_ENV['TORII_SECRET_KEY']);
 *     $user = $torii->users->get($userId);
 *
 * Default api URL is `https://api.torii.so`. Override with the second argument
 * for staging or self-hosted.
 */
final class Torii
{
    public readonly Users $users;
    public readonly Sessions $sessions;

    public const DEFAULT_API_URL = 'https://api.torii.so';

    private function __construct(
        ServerUsersApi $usersApi,
        ServerSessionsApi $sessionsApi,
        ClientInterface $httpClient,
        string $host,
    ) {
        $this->users = new Users($usersApi, $httpClient, $host);
        $this->sessions = new Sessions($sessionsApi);
    }

    /**
     * Build a torii backend client.
     *
     * @param string $secretKey Backend secret key (e.g. `sk_live_...`). Required.
     * @param string|null $apiUrl Backend API base URL. Defaults to
     *                            `https://api.torii.so`.
     * @param ClientInterface|null $httpClient Optional PSR-18 client. Mostly
     *                                         useful for testing — pass a mock
     *                                         or a pre-configured Guzzle
     *                                         instance. Auth is applied from
     *                                         the generated `bearerAuth` scheme
     *                                         (the secret key on the config),
     *                                         so a custom client needs no auth
     *                                         wiring of its own.
     */
    public static function create(
        string $secretKey,
        ?string $apiUrl = null,
        ?ClientInterface $httpClient = null,
    ): self {
        if ($secretKey === '') {
            throw new \InvalidArgumentException('Torii::create: secretKey is required');
        }

        $host = rtrim($apiUrl ?? self::DEFAULT_API_URL, '/');
        // The spec declares a `bearerAuth` scheme; the generated operations
        // send `Authorization: Bearer <accessToken>` from the config. The
        // hand-rolled PATCH reads the same token off the config.
        $config = (new Configuration())->setHost($host)->setAccessToken($secretKey);

        $client = $httpClient ?? new GuzzleClient(['timeout' => 30.0, 'http_errors' => true]);

        return new self(
            usersApi: new ServerUsersApi($client, $config),
            sessionsApi: new ServerSessionsApi($client, $config),
            httpClient: $client,
            host: $host,
        );
    }
}
