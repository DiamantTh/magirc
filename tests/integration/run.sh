#!/usr/bin/env sh
set -eu

test_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
php "$test_dir/../Integration/IsolatedDatabaseGuard.php"

"$test_dir/../../vendor/bin/phpunit" --testsuite "MagIRC integration tests" --fail-on-skipped
