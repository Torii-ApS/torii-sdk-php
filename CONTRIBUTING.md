# Contributing

Thanks for your interest in `torii-sdk-php`!

## Reporting bugs

Open an issue with:

- The version of `torii/backend` you're using (`composer show torii/backend`).
- A minimal reproduction — a few lines that exhibit the bug.
- What you expected to happen vs. what actually happened.

For security-sensitive issues (anything that could let an attacker forge or bypass token verification), please email **security@torii.so** instead of filing a public issue.

## Development

```sh
git clone https://github.com/GOOD-Code-ApS/torii-sdk-php
cd torii-sdk-php
composer install
vendor/bin/phpunit
```

The REST client under `src/Generated/` is produced by [`openapi-generator`](https://openapi-generator.tech/) from `spec/server-v1.json`. Don't hand-edit it. To regenerate after a spec update:

```sh
npx -y @openapitools/openapi-generator-cli generate \
  -i spec/server-v1.json -g php -o src/Generated \
  --additional-properties=invokerPackage=Torii\\Backend\\Generated,packageName=torii-backend-generated,gitUserId=GOOD-Code-ApS,gitRepoId=torii
# Delete docs/, test/, composer.json, README.md, .travis.yml, .openapi-generator/, etc. afterwards.
```

The hand-written surface is where bug reports and PRs typically land:

- `src/Torii.php` — top-level client/factory.
- `src/Users.php`, `src/Sessions.php` — thin wrappers over the generated REST client.
- `src/Verify.php` — networkless JWT verification via `firebase/php-jwt`'s `CachedKeySet`.
- `src/AuthenticateRequest.php` — PSR-7 / header-array request authentication helper.
- `src/Webhook.php` — outbound webhook verification (stub pending torii's webhook subsystem).
- `src/Auth.php` — the verified-claims value object.
- `src/ApiException.php`, `src/AuthException.php` — exception types.
- `src/Internal/ArrayCachePool.php` — default in-memory PSR-6 cache for JWKS.
- `src/Laravel/` — `ToriiServiceProvider` + `RequireAuth` middleware.

## Pull requests

1. Open an issue first for non-trivial changes so we can discuss the shape.
2. Branch off `main`, name it `fix/<short>` or `feat/<short>`.
3. Run `vendor/bin/phpunit` before pushing — CI runs against PHP 8.2, 8.3, 8.4.
4. Keep PRs small and focused. One concern per PR.
5. Update `README.md` if you change the public surface.

## Releases

Tagged off `main`. Bump any version references in `README.md`, then:

```sh
git tag v0.0.2
git push origin v0.0.2
```

Packagist picks up the new tag automatically; consumers update via `composer update torii/backend`.

## Code of Conduct

Be kind. Disagreements happen; argue the position, not the person.
