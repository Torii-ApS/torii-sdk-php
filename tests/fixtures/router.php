<?php

declare(strict_types=1);

/**
 * Test fixture server used by VerifyTest and AuthenticateRequestTest.
 *
 * Reads a JWKS document from $_ENV['TORII_TEST_JWKS_PATH'] (set by the test
 * harness before spawning `php -S 127.0.0.1:<port> router.php`) and serves
 * it at `/_torii/.well-known/jwks.json`. Everything else 404s.
 *
 * Keeping this dirt-simple — no routing libs, no Symfony Process — so the
 * test setup is debuggable from the command line.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/_torii/.well-known/jwks.json') {
    $jwksFile = getenv('TORII_TEST_JWKS_PATH');
    if (!$jwksFile || !is_file($jwksFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'jwks file missing']);
        return true;
    }
    header('Content-Type: application/json');
    readfile($jwksFile);
    return true;
}

http_response_code(404);
echo json_encode(['error' => 'not found', 'path' => $path]);
return true;
