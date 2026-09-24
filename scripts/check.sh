#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$root"

mode=${1:-read-only}
case "$mode" in
    read-only|isolated) ;;
    *) echo "Usage: sh scripts/check.sh [read-only|isolated]" >&2; exit 2 ;;
esac

if [ "$mode" = isolated ]; then
    php tests/Integration/IsolatedDatabaseGuard.php
fi

composer validate --strict --no-check-publish
composer check-platform-reqs
php -r 'if (PHP_VERSION_ID < 80400) { fwrite(STDERR, "PHP 8.4 or newer is required.\n"); exit(2); } foreach (["dom", "gettext", "mbstring", "pdo_mysql", "xml"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing required PHP extension: $extension\n"); exit(2); } }'
composer test
composer cs
composer analyse
composer rector:check
composer audit --locked --no-interaction
yarn test:editor
yarn test:runtime-assets

if [ "$mode" = isolated ]; then
    composer test:integration
    composer test:installation
    sh tests/http/run.sh
fi

echo "MagIRC $mode checks: OK"
