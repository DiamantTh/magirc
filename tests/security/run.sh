#!/bin/sh
set -eu
test_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
php "$test_dir/core_test.php"
php "$test_dir/login_test.php"
php "$test_dir/installer_test.php"
