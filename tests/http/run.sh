#!/bin/sh
set -eu

test_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
root=$(CDPATH= cd -- "$test_dir/../.." && pwd)
php "$root/tests/Integration/IsolatedDatabaseGuard.php"

umask 077
work=$(mktemp -d)
app="$work/app"
mkdir -p "$app/conf" "$app/tmp"
touch "$app/.magirc-http-fixture"
server_pid=
cleanup() {
    if [ -n "$server_pid" ]; then
        kill "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
    php "$test_dir/fixture.php" cleanup "$app" || true
    rm -rf -- "$work"
}
trap cleanup EXIT HUP INT TERM

for path in admin assets index.php js lib locale rest setup src theme vendor; do
    cp -a "$root/$path" "$app/$path"
done

port=$(php -r '$socket = stream_socket_server("tcp://127.0.0.1:0", $error, $message); if (!$socket) { exit(1); } echo substr(strrchr(stream_socket_get_name($socket, false), ":"), 1); fclose($socket);')
php -S "127.0.0.1:$port" -t "$app" > "$work/server.log" 2>&1 &
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

php "$test_dir/assert-unconfigured.php" "http://127.0.0.1:$port"
php "$test_dir/fixture.php" setup "$app"
if ! php "$test_dir/assert.php" "http://127.0.0.1:$port" "$app"; then
    cat "$work/server.log" >&2
    if [ -f "$app/tmp/magirc.log" ]; then
        tail -n 15 "$app/tmp/magirc.log" >&2
    fi
    exit 1
fi
