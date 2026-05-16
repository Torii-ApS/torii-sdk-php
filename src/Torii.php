<?php

declare(strict_types=1);

namespace Torii\Backend;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
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
    ) {
        $this->users = new Users($usersApi);
        $this->sessions = new Sessions($sessionsApi);
    }

    /**
     * Build a torii backend client.
     *
     * @param string $secretKey Backend secret key (e.g. `sk_live_...`). Required.
     * @param string|null $apiUrl Backend API base URL. Defaults to
     *                            `https://api.torii.so`.
     * @param ClientInterface|null $httpClient Optional PSR-18 client. Mostly
     *                                         useful for testing — pass a
     *                                         mock or a pre-configured Guzzle
     *                                         instance. If supplied, the
     *                                         caller is responsible for
     *                                         attaching the bearer header;
     *                                         when null, we create a Guzzle
     *                                         client wired up with auth.
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
        $config = (new Configuration())->setHost($host);

        $client = $httpClient ?? self::buildDefaultHttpClient($secretKey);

        return new self(
            usersApi: new ServerUsersApi($client, $config),
            sessionsApi: new ServerSessionsApi($client, $config),
        );
    }

    /**
     * Guzzle client wired up with secret-key auth + JSON accept header.
     *
     * Using a handler-stack middleware (rather than baking the header into
     * default options) so callers passing their own ClientInterface can layer
     * extra middleware without losing auth.
     */
    private static function buildDefaultHttpClient(string $secretKey): GuzzleClient
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::mapRequest(static function (RequestInterface $request) use ($secretKey): RequestInterface {
            return $request
                ->withHeader('Authorization', 'Bearer ' . $secretKey)
                ->withHeader('Accept', 'application/json');
        }), 'torii-auth');

        return new GuzzleClient([
            'handler' => $stack,
            'timeout' => 30.0,
            'http_errors' => true,
        ]);
    }
}
