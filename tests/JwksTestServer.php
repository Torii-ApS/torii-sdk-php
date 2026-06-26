<?php

declare(strict_types=1);

namespace Torii\Backend\Tests;

use Symfony\Component\Process\Process;

/**
 * Spawns an in-process `php -S` server that serves a JWKS document on
 * `/_torii/.well-known/jwks.json`. Each instance generates its own ES256
 * keypair so tests can't cross-contaminate.
 *
 * Use {@see issueToken()} to sign JWTs with the same key — that's the only
 * way the verifier under test will accept them.
 */
final class JwksTestServer
{
    private Process $process;
    private string $jwksFile;

    public function __construct(
        public readonly string $privateKeyPem,
        public readonly string $publicKeyPem,
        public readonly string $kid,
        public readonly int $port,
    ) {
        $this->jwksFile = tempnam(sys_get_temp_dir(), 'torii-jwks-');
        file_put_contents($this->jwksFile, self::buildJwksJson($publicKeyPem, $kid));

        $router = __DIR__ . '/fixtures/router.php';
        $this->process = new Process(['php', '-S', '127.0.0.1:' . $port, $router]);
        $this->process->setEnv(['TORII_TEST_JWKS_PATH' => $this->jwksFile]);
        $this->process->start();

        // Wait for server to be ready (up to 5s).
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($sock !== false) {
                fclose($sock);
                return;
            }
            usleep(50_000);
        }
        $this->process->stop();
        throw new \RuntimeException("Test JWKS server failed to start on port $port: " . $this->process->getErrorOutput());
    }

    public static function start(): self
    {
        // Generate ES256 keypair.
        $pkey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($pkey === false) {
            throw new \RuntimeException('openssl_pkey_new failed: ' . openssl_error_string());
        }

        openssl_pkey_export($pkey, $privatePem);
        $details = openssl_pkey_get_details($pkey);
        $publicPem = $details['key'];

        $port = self::pickFreePort();
        $kid = 'test-key-' . bin2hex(random_bytes(4));

        return new self($privatePem, $publicPem, $kid, $port);
    }

    public function issuerUrl(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    public function stop(): void
    {
        $this->process->stop(2.0);
        if (is_file($this->jwksFile)) {
            @unlink($this->jwksFile);
        }
    }

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Build a JWKS JSON document containing the public key as a P-256 EC JWK.
     */
    private static function buildJwksJson(string $publicPem, string $kid): string
    {
        $key = openssl_pkey_get_public($publicPem);
        if ($key === false) {
            throw new \RuntimeException('Failed to load public key');
        }
        $details = openssl_pkey_get_details($key);
        $x = $details['ec']['x'] ?? null;
        $y = $details['ec']['y'] ?? null;
        if ($x === null || $y === null) {
            throw new \RuntimeException('Public key is not EC');
        }
        // Pad to 32 bytes (P-256 component size) then base64url-encode.
        $b64u = static fn(string $bytes): string => rtrim(strtr(base64_encode(
            str_pad($bytes, 32, "\x00", STR_PAD_LEFT)
        ), '+/', '-_'), '=');

        return json_encode([
            'keys' => [[
                'kty' => 'EC',
                'crv' => 'P-256',
                'alg' => 'ES256',
                'use' => 'sig',
                'kid' => $kid,
                'x' => $b64u($x),
                'y' => $b64u($y),
            ]],
        ], JSON_THROW_ON_ERROR);
    }

    private static function pickFreePort(): int
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($sock === false) {
            throw new \RuntimeException('socket_create failed');
        }
        socket_bind($sock, '127.0.0.1', 0);
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);
        return $port;
    }
}
