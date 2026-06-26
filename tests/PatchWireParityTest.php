<?php

declare(strict_types=1);

namespace Torii\Backend\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Torii\Backend\Patch;
use Torii\Backend\Torii;

/**
 * Wire-parity against the shared contract fixtures
 * (contract-tests/fixtures/patch-wire). For each UpdateUserRequest fixture we
 * build a Patch-keyed array from expectedBody and assert the SDK PATCHes exactly
 * those bytes — the same fixtures the server round-trip test asserts. Covers the
 * tri-state path (set / clear / omit) and the metadata key-delete case.
 */
final class PatchWireParityTest extends TestCase
{
    private const SAMPLE_USER_JSON = <<<'JSON'
    {
        "id": "11111111-1111-1111-1111-111111111111",
        "environmentId": "22222222-2222-2222-2222-222222222222",
        "name": "Ada Lovelace",
        "firstName": "Ada",
        "lastName": "Lovelace",
        "locale": "en",
        "status": "active",
        "createdAt": "2024-01-01T00:00:00Z",
        "updatedAt": "2024-01-02T00:00:00Z",
        "email": "ada@example.com",
        "emailVerifiedAt": null,
        "deletedAt": null,
        "publicMetadata": {},
        "privateMetadata": {},
        "unsafeMetadata": {}
    }
    JSON;

    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];

    /** @return iterable<string, array{0: array<string, mixed>}> */
    public static function updateFixtures(): iterable
    {
        $raw = file_get_contents(__DIR__ . '/patch_wire_fixtures.json');
        $manifest = json_decode((string) $raw, true);
        foreach ($manifest['fixtures'] as $fixture) {
            // The PHP SDK's tri-state path is users->update (UpdateUserRequest);
            // it has no updateMetadata method, so cover the update fixtures here.
            if (($fixture['schema'] ?? '') === 'UpdateUserRequest') {
                yield $fixture['name'] => [$fixture['expectedBody']];
            }
        }
    }

    /**
     * @param array<string, mixed> $expectedBody
     */
    #[Test]
    #[DataProvider('updateFixtures')]
    public function sdk_emits_blessed_wire_bytes(array $expectedBody): void
    {
        $this->history = [];
        $mock = new MockHandler([new Response(200, [], self::SAMPLE_USER_JSON)]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $client = new GuzzleClient(['handler' => $stack, 'http_errors' => false]);
        $torii = Torii::create('sk_test_abc', 'https://api.example', $client);

        // Build the Patch-keyed input generically from the fixture: a present key
        // (incl. one mapped to null) becomes Patch::set(value); an absent key is
        // simply never added, so it is omitted from the wire body.
        $patches = [];
        foreach ($expectedBody as $key => $value) {
            $patches[$key] = Patch::set($value);
        }

        $torii->users->update('11111111-1111-1111-1111-111111111111', $patches);

        $request = $this->history[count($this->history) - 1]['request'];
        $this->assertSame(
            $expectedBody,
            json_decode((string) $request->getBody(), true),
        );
    }
}
