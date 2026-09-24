#!/bin/sh
set -eu

test_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
root=$(CDPATH= cd -- "$test_dir/../.." && pwd)
if [ ! -f "$root/assets/vendor/jquery/jquery.min.js" ] || [ ! -f "$root/vendor/autoload.php" ]; then
    echo 'Installation smoke test requires built runtime assets and Composer dependencies.' >&2
    exit 2
fi

umask 077
work=$(mktemp -d)
trap 'rm -rf -- "$work"' EXIT HUP INT TERM
app="$work/app"
mkdir -p "$app/conf" "$app/tmp"
for path in assets lib setup vendor; do
    cp -a "$root/$path" "$app/$path"
done

REQUEST_METHOD=GET php "$app/setup/index.php" > "$work/response.html"
grep -q 'Requirements check' "$work/response.html"
grep -q 'Checking PHP version' "$work/response.html"
grep -q '>Supported</span>' "$work/response.html"
echo 'Isolated installation entrypoint smoke: OK'
