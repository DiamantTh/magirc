#!/usr/bin/env sh
set -eu

if [ -z "${MAGIRC_TEST_DSN:-}" ]; then
    echo "database integration tests: SKIPPED (set MAGIRC_TEST_DSN to a MySQL/MariaDB test database)"
    exit 0
fi

vendor/bin/phpunit --testsuite "MagIRC integration tests"
