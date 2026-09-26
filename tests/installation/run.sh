#!/bin/sh
set -eu

test_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
root=$(CDPATH= cd -- "$test_dir/../.." && pwd)
if [ ! -f "$root/httpdocs/assets/vendor/jquery/jquery.min.js" ] || [ ! -f "$root/vendor/autoload.php" ]; then
    echo 'Installation smoke test requires built runtime assets and Composer dependencies.' >&2
    exit 2
fi

umask 077
work=$(mktemp -d)
trap 'rm -rf -- "$work"' EXIT HUP INT TERM
app="$work/app"
mkdir -p "$app/conf" "$app/tmp"
server_pid=
cleanup() {
    if [ -n "$server_pid" ]; then
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
    rm -rf -- "$work"
}
trap cleanup EXIT HUP INT TERM
for path in httpdocs src themes templates resources locale vendor; do
    cp -a "$root/$path" "$app/$path"
done

port=$(php -r '$socket = stream_socket_server("tcp://127.0.0.1:0", $error, $message); if (!$socket) { exit(1); } echo substr(strrchr(stream_socket_get_name($socket, false), ":"), 1); fclose($socket);')
MAGIRC_TEST_HTTPDOCS="$app/httpdocs" php -S "127.0.0.1:$port" -t "$app/httpdocs" "$root/tests/http/router.php" > "$work/server.log" 2>&1 &
server_pid=$!

ready=0
for attempt in 1 2 3 4 5 6 7 8 9 10; do
    if php -r '$socket = @fsockopen("127.0.0.1", (int) $argv[1], $error, $message, 1); if (!$socket) { exit(1); } fclose($socket);' "$port"; then
        ready=1
        break
    fi
    sleep 1
done
if [ "$ready" -ne 1 ]; then
    cat "$work/server.log" >&2
    echo 'Isolated PHP web server did not start.' >&2
    exit 1
fi

php -r '$context = stream_context_create(["http" => ["ignore_errors" => true]]); $body = file_get_contents($argv[1], false, $context); if (!is_string($body)) { exit(1); } echo $body;' "http://127.0.0.1:$port/setup/" > "$work/response.html"
grep -q 'Requirements check' "$work/response.html"
grep -q 'Checking PHP version' "$work/response.html"
grep -q '>Supported</span>' "$work/response.html"
echo 'Isolated installation entrypoint smoke: OK'
