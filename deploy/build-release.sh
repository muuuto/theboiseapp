#!/usr/bin/env sh
# Builds release/theboise-<date>-<commit>.tar.gz to upload to the shared hosting with cPanel File Manager.
# Needs only Docker: dependencies are installed with the same PHP version as the server.
#
# Usage: ./deploy/build-release.sh [commit]   (default: HEAD, the last commit)
# Only committed files are packaged: uncommitted work in progress never ends up on the server.
set -eu

cd "$(dirname "$0")/.."
PHP_IMAGE=laravelsail/php84-composer:latest
REF="${1:-HEAD}"
COMMIT="$(git rev-parse --short "$REF")"
NAME="theboise-$(date +%Y-%m-%d_%H%M)-$COMMIT"
BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

if [ -n "$(git status --porcelain)" ]; then
    echo "Note: you have uncommitted changes, they are NOT included. Packaging commit $COMMIT."
fi

git archive --format=tar "$REF" | tar -xf - -C "$BUILD"

# Left out:
#  - .htaccess: its "SP php handler" block is managed by cPanel MultiPHP, uploading ours could switch PHP version
#  - local development and test files
(cd "$BUILD" && rm -rf .htaccess .cpanel.yml docker-compose.yml phpunit.xml tests deploy notes.md TODO.md .editorconfig .gitattributes)

# Install production dependencies only. This also regenerates bootstrap/cache/packages.php and
# services.php, which replace the stale ones on the server.
docker run --rm -u "$(id -u):$(id -g)" -v "$BUILD:/app" -w /app -e COMPOSER_HOME=/tmp/composer "$PHP_IMAGE" \
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress

mkdir -p release
tar -czf "release/$NAME.tar.gz" -C "$BUILD" .

echo "Built release/$NAME.tar.gz ($(du -h "release/$NAME.tar.gz" | cut -f1))"
