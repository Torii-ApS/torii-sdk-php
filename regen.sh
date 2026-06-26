#!/usr/bin/env bash
# Regenerate the generated REST client under src/Generated/ from
# spec/server-v1.json, then strip generator scaffolding the SDK doesn't ship.
# Idempotent; safe to re-run after a spec bump. Generates into a temp dir and
# swaps the result in, so a removed endpoint can't leave an orphaned file and a
# failed generate can't leave a half-stripped tree under src/Generated.
#
# The PHP namespace contains backslashes, which don't survive shell escaping
# through --additional-properties, so they're passed via a JSON config file.
set -euo pipefail
cd "$(dirname "$0")"

STAGE=$(mktemp -d)
CFG=$(mktemp)
trap 'rm -rf "$STAGE" "$CFG"' EXIT
cat > "$CFG" <<'JSON'
{
  "invokerPackage": "Torii\\Backend\\Generated",
  "packageName": "torii-backend-generated"
}
JSON

npx -y @openapitools/openapi-generator-cli generate \
  -i spec/server-v1.json -g php -o "$STAGE" \
  -c "$CFG"

# Strip generator-emitted scaffolding the SDK doesn't ship.
( cd "$STAGE" && rm -rf docs test composer.json README.md .travis.yml \
  .openapi-generator .openapi-generator-ignore .gitignore \
  phpunit.xml.dist git_push.sh .php-cs-fixer.dist.php )

# Validate non-empty BEFORE replacing the committed tree, then swap it in.
if [ -z "$(ls -A "$STAGE")" ]; then
  echo "✗ php: generator produced no output; leaving src/Generated intact" >&2
  exit 1
fi
rm -rf src/Generated
mv "$STAGE" src/Generated

echo "✓ regenerated src/Generated/ from spec/server-v1.json"
