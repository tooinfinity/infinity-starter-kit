#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 '<answers-json>'" >&2
    exit 1
fi

ANSWERS="$1"
echo "=== [verify-combo] Verifying combination ==="
echo "Answers: ${ANSWERS}"

SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEMP_DIR="$(mktemp -d -t chisel-combo-XXXXXX)"

cleanup() {
    local exit_code=$?
    echo "=== [verify-combo] Cleaning up temporary directory: ${TEMP_DIR} ==="
    rm -rf "${TEMP_DIR}"
    exit "${exit_code}"
}
trap cleanup EXIT INT TERM

echo "=== [verify-combo] Mirroring repository to temporary directory ==="
rsync -a \
    --exclude="vendor" \
    --exclude="node_modules" \
    --exclude=".git" \
    --exclude="database.sqlite" \
    --exclude="database/database.sqlite" \
    --exclude="tmp" \
    --exclude=".phpunit.cache" \
    "${SOURCE_DIR}/" "${TEMP_DIR}/"

cd "${TEMP_DIR}"

cp -n .env.example .env
mkdir -p database
touch database/database.sqlite

# If source repo already has vendor/node_modules, seed them using copy-on-write reflinks for speed
if [[ -d "${SOURCE_DIR}/vendor" ]]; then
    cp -a --reflink=auto "${SOURCE_DIR}/vendor" ./vendor 2>/dev/null || true
fi
if [[ -d "${SOURCE_DIR}/node_modules" ]]; then
    cp -a --reflink=auto "${SOURCE_DIR}/node_modules" ./node_modules 2>/dev/null || true
fi

echo "=== [verify-combo] Installing Composer dependencies ==="
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "=== [verify-combo] Generating application key ==="
php artisan key:generate --ansi

echo "=== [verify-combo] Installing frontend dependencies ==="
bun install

echo "=== [verify-combo] Running feature installer ==="
php artisan install:features --no-interaction --answers="${ANSWERS}"

echo "=== [verify-combo] Running composer test suite ==="
composer test

echo "=== [verify-combo] Combination verified successfully! ==="
