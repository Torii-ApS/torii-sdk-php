<?php

declare(strict_types=1);

namespace Torii\Backend\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Torii\Backend\Patch;
use Torii\Backend\Torii;

/**
 * Verify that {@see \Torii\Backend\Users::update()} translates a `Patch`-keyed
 * array into the expected PATCH request body — the whole point of the
 * tri-state wrapper.
 *
 * The Guzzle MockHandler captures the outgoing request via a history
 * middleware so we can inspect URL, headers, and body without hitting the
 * network.
 */
final class UsersUpdateTest extends TestCase
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

    private function makeTorii(): Torii
    {
        $this->history = [];
        $mock = new MockHandler([new Response(200, [], self::SAMPLE_USER_JSON)]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));
        $client = new GuzzleClient(['handler' => $stack, 'http_errors' => false]);
        return Torii::create('sk_test_abc', 'https://api.example', $client);
    }

    private function lastRequest(): RequestInterface
    {
        $this->assertNotEmpty($this->history, 'No HTTP request was captured');
        return $this->history[count($this->history) - 1]['request'];
    }

    #[Test]
    public function patch_set_sends_field_with_value(): void
    {
        $torii = $this->makeTorii();

        $torii->users->update('11111111-1111-1111-1111-111111111111', [
            'firstName' => Patch::set('Ada'),
        ]);

        $this->assertSame(
            ['firstName' => 'Ada'],
            json_decode((string) $this->lastRequest()->getBody(), true),
        );
    }

    #[Test]
    public function patch_clear_sends_field_with_null(): void
    {
        $torii = $this->makeTorii();

        $torii->users->update('11111111-1111-1111-1111-111111111111', [
            'lastName' => Patch::set(null),
        ]);

        $decoded = json_decode((string) $this->lastRequest()->getBody(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('lastName', $decoded);
        $this->assertNull($decoded['lastName']);
    }

    #[Test]
    public function omitting_field_leaves_it_out_of_body(): void
    {
        $torii = $this->makeTorii();

        $torii->users->update('11111111-1111-1111-1111-111111111111', [
            'firstName' => Patch::set('Ada'),
        ]);

        $decoded = json_decode((string) $this->lastRequest()->getBody(), true);
        $this->assertArrayNotHasKey('lastName', $decoded);
        $this->assertArrayNotHasKey('locale', $decoded);
    }

    #[Test]
    public function empty_patch_array_sends_empty_json_object(): void
    {
        $torii = $this->makeTorii();

        $torii->users->update('11111111-1111-1111-1111-111111111111', []);

        // Must be `{}` (object), not `[]` (array) — the API expects JSON object.
        $this->assertSame('{}', (string) $this->lastRequest()->getBody());
    }

    #[Test]
    public function mixed_set_and_clear_serializes_correctly(): void
    {
        $torii = $this->makeTorii();

        $torii->users->update('11111111-1111-1111-1111-111111111111', [
            'firstName' => Patch::set('Ada'),
            'lastName' => Patch::set(null),
            'locale' => Patch::set('en'),
        ]);

        $decoded = json_decode((string) $this->lastRequest()->getBody(), true);
        $this->assertSame(
            ['firstName' => 'Ada', 'lastName' => null, 'locale' => 'en'],
            $decoded,
        );
    }

    #[Test]
    public function patch_set_unsafe_metadata_serialises_as_object(): void
    {
        $torii = $this->makeTorii();

        $torii->users->update('11111111-1111-1111-1111-111111111111', [
            'unsafeMetadata' => Patch::set(['tier' => 'pro']),
        ]);

        // The bag must serialise as a JSON object, and tri-state is preserved.
        $this->assertSame(
            '{"unsafeMetadata":{"tier":"pro"}}',
            (string) $this->lastRequest()->getBody(),
        );
    }

    #[Test]
    public function non_patch_value_throws_invalid_argument(): void
    {
        $torii = $this->makeTorii();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("field 'firstName' must be a Torii\\Backend\\Patch instance");

        /** @phpstan-ignore-next-line — deliberately bad input */
        $torii->users->update('11111111-1111-1111-1111-111111111111', ['firstName' => 'Ada']);
    }

    #[Test]
    public function update_hits_patch_endpoint_with_user_id(): void
    {
        $torii = $this->makeTorii();

        $torii->users->update('11111111-1111-1111-1111-111111111111', [
            'firstName' => Patch::set('Ada'),
        ]);

        $req = $this->lastRequest();
        $this->assertSame('PATCH', $req->getMethod());
        $this->assertSame(
            'https://api.example/api/server/v1/users/11111111-1111-1111-1111-111111111111',
            (string) $req->getUri(),
        );
        $this->assertSame('application/json', $req->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function update_carries_bearer_auth_on_the_hand_rolled_path(): void
    {
        // The PATCH path bypasses the generated client, so it must attach the
        // bearer token itself (read from the same config the generated calls use).
        $torii = $this->makeTorii();

        $torii->users->update('11111111-1111-1111-1111-111111111111', [
            'firstName' => Patch::set('Ada'),
        ]);

        $this->assertSame('Bearer sk_test_abc', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    #[Test]
    public function create_uses_generated_client_with_bearer_and_omits_metadata(): void
    {
        // create() runs through the generated createUser: auth comes from the
        // bearerAuth scheme, and unset metadata bags are omitted (the server
        // defaults them to {}), so no hand-defaulting.
        $torii = $this->makeTorii();

        $torii->users->create(['email' => 'ada@example.com']);

        $req = $this->lastRequest();
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('https://api.example/api/server/v1/users', (string) $req->getUri());
        $this->assertSame('Bearer sk_test_abc', $req->getHeaderLine('Authorization'));
        $this->assertSame(
            ['email' => 'ada@example.com'],
            json_decode((string) $req->getBody(), true),
        );
    }
}
