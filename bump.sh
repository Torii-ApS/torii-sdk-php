#!/usr/bin/env bash
# Packagist derives the published version from the git tag, and the recommended
# practice is to NOT pin a `version` field in composer.json. So there is nothing
# to edit here. This script exists only so the release train can call ./bump.sh
# uniformly across all 7 SDKs.
set -euo pipefail

VERSION="${1:?usage: ./bump.sh <version>  (e.g. 0.0.5)}"
VERSION="${VERSION#v}"
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.]+)?$ ]]; then
	echo "✗ invalid version: '$VERSION'" >&2
	exit 1
fi

echo "✓ torii-sdk-php -> $VERSION (no version field; Packagist derives from the git tag)"
