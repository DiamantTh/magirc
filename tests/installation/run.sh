#!/usr/bin/env sh
set -eu

if [ ! -f assets/vendor/jquery/jquery.min.js ]; then
    echo "installation smoke tests: SKIPPED (run yarn build:runtime first)"
    exit 0
fi

output_file=$(mktemp)
trap 'rm -f "$output_file"' EXIT
REQUEST_METHOD=GET php setup/index.php > "$output_file"
grep -q 'Requirements check' "$output_file"
grep -q 'Checking PHP version' "$output_file"
grep -q '>Supported</span>' "$output_file"
echo "installation entrypoint smoke: OK"
