<?php

declare(strict_types=1);

$baseUrl = $argv[1] ?? '';
$appRoot = $argv[2] ?? '';
if (!str_starts_with($baseUrl, 'http://127.0.0.1:')
    || !str_starts_with((string) realpath($appRoot), (string) realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR)
    || !is_file($appRoot . '/.magirc-http-fixture') || !is_dir($appRoot . '/conf')) {
    fwrite(STDERR, "Usage: php tests/http/assert.php http://127.0.0.1:PORT ISOLATED_APP_ROOT\n");
    exit(2);
}

$cookies = [];

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{status:int,headers:array<string,list<string>>,body:string} */
function request(string $path, string $method = 'GET', array $headers = [], ?array $form = null, bool $sendCookies = true): array
{
    global $baseUrl, $cookies;

    if ($sendCookies && $cookies !== []) {
        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $headers[] = 'Cookie: ' . implode('; ', $pairs);
    }
    if ($form !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $form === null ? '' : http_build_query($form),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 8,
    ]]);
    $body = @file_get_contents($baseUrl . $path, false, $context);
    if ($body === false || !isset($http_response_header[0]) || !preg_match('~^HTTP/\S+ (\d{3})~', $http_response_header[0], $match)) {
        throw new RuntimeException("HTTP request failed: $method $path");
    }
    $parsedHeaders = [];
    foreach (array_slice($http_response_header, 1) as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }
        [$name, $value] = explode(':', $line, 2);
        $parsedHeaders[strtolower($name)][] = trim($value);
        if (strtolower($name) === 'set-cookie' && preg_match('/^([^=;]+)=([^;]*)/', trim($value), $cookie)) {
            $cookies[$cookie[1]] = $cookie[2];
        }
    }
    return ['status' => (int) $match[1], 'headers' => $parsedHeaders, 'body' => $body];
}

function headerValue(array $response, string $name): string
{
    return $response['headers'][strtolower($name)][0] ?? '';
}

function csrfFields(string $html): array
{
    $fields = [];
    foreach (['csrf_name', 'csrf_value'] as $name) {
        if (!preg_match('/name="' . $name . '" value="([^"]+)"/', $html, $match)) {
            throw new RuntimeException("Missing $name in the rendered admin form.");
        }
        $fields[$name] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $fields;
}

try {
    $front = request('/index.php/');
    check($front['status'] === 200 && str_contains($front['body'], 'Network'), 'Public Twig page did not render.');
    check(headerValue($front, 'X-Content-Type-Options') === 'nosniff', 'Public response is missing nosniff.');
    check(str_contains(headerValue($front, 'Content-Security-Policy'), "object-src 'none'"), 'Public response is missing CSP.');

    $public = request('/rest/service.php/network/status', 'GET', [], null, false);
    check($public['status'] === 200, 'Public REST status did not return 200.');
    check((json_decode($public['body'], true)['users']['val'] ?? null) === 12, 'REST status did not return the Anope fixture.');
    check(headerValue($public, 'Cache-Control') === 'public, max-age=30', 'Public statistics cache header is incorrect.');
    $etag = headerValue($public, 'ETag');
    check($etag !== '', 'Public statistics have no ETag.');
    $conditional = request('/rest/service.php/network/status', 'GET', ['If-None-Match: ' . $etag], null, false);
    check($conditional['status'] === 304 && $conditional['body'] === '', 'Conditional public statistics did not return an empty 304.');

    $hidden = request('/rest/service.php/channels/%23hidden');
    check($hidden['status'] === 403 && str_contains($hidden['body'], 'Access Denied'), 'Hidden channel was publicly reachable.');
    check(str_contains(headerValue($hidden, 'Cache-Control'), 'no-store'), 'Protected channel response is cacheable.');
    $missing = request('/rest/service.php/does-not-exist');
    check($missing['status'] === 404, 'Unknown REST route returned ' . $missing['status'] . ': ' . $missing['body']);

    $cookies = [];
    $admin = request('/admin/index.php/overview');
    check($admin['status'] === 200 && str_contains($admin['body'], 'Login'), 'Anonymous admin login page did not render.');
    check(str_contains(headerValue($admin, 'Cache-Control'), 'no-store'), 'Admin login is cacheable.');
    check(str_contains(implode(' ', $admin['headers']['set-cookie'] ?? []), 'HttpOnly'), 'Session cookie lacks HttpOnly.');
    check(str_contains(implode(' ', $admin['headers']['set-cookie'] ?? []), 'SameSite=Lax'), 'Session cookie lacks SameSite=Lax.');
    $oldCookies = $cookies;
    $protected = request('/admin/index.php/configuration/welcome');
    check($protected['status'] === 403, 'Anonymous request reached an admin configuration page.');
    $badLogin = request('/admin/index.php/login', 'POST', [], ['username' => 'ci-admin', 'password' => 'Isolated-Test-Passphrase-2026']);
    check($badLogin['status'] === 403, 'Login accepted a missing CSRF token.');

    $admin = request('/admin/index.php/overview');
    $login = request('/admin/index.php/login', 'POST', [], csrfFields($admin['body']) + [
        'username' => 'ci-admin', 'password' => 'Isolated-Test-Passphrase-2026',
    ]);
    check($login['status'] === 303, 'Valid administrator login did not redirect.');
    check($cookies !== $oldCookies, 'Login did not renew the session cookie.');
    $overview = request('/admin/index.php/overview');
    check($overview['status'] === 200 && str_contains($overview['body'], 'Logout'), 'Authenticated overview did not render.');
    $welcome = request('/admin/index.php/configuration/welcome');
    check($welcome['status'] === 200, 'Authenticated welcome configuration is unavailable.');
    check(str_contains(headerValue($welcome, 'Cache-Control'), 'no-store'), 'Protected admin response is cacheable.');

    $private = request('/rest/service.php/network/status', 'GET', ['If-None-Match: ' . $etag]);
    check($private['status'] === 200, 'Authenticated statistics incorrectly returned 304.');
    check(str_contains(headerValue($private, 'Cache-Control'), 'no-store'), 'Authenticated statistics are publicly cacheable.');
    $authorizedHeader = request('/rest/service.php/network/status', 'GET', ['Authorization: Bearer invalid'], null, false);
    check(str_contains(headerValue($authorizedHeader, 'Cache-Control'), 'no-store'), 'Authorized request is publicly cacheable.');

    $logout = request('/admin/index.php/logout', 'POST', [], csrfFields($overview['body']));
    check($logout['status'] === 303, 'Logout did not redirect.');
    check(request('/admin/index.php/configuration/welcome')['status'] === 403, 'Logged-out session still reaches administration.');
    $cookies = ['PHPSESSID' => 'attacker-controlled-session-id'];
    check(request('/admin/index.php/configuration/welcome')['status'] === 403, 'Manipulated session cookie grants admin access.');

    check(request('/setup/index.php')['status'] === 404, 'Completed installation leaves Setup enabled.');
    check(request('/setup/index.php', 'POST', [], ['savedb' => '1'])['status'] === 403, 'Installer POST accepted a missing CSRF token.');
    check(request('/admin/js/welcome-editor.bundle.js')['status'] === 200, 'Tiptap bundle is unavailable over HTTP.');

    $configPath = $appRoot . '/conf/magirc.json';
    $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
    $config['port'] = 1;
    file_put_contents($configPath, json_encode($config, JSON_THROW_ON_ERROR));
    $failedDatabase = request('/rest/service.php/network/status', 'GET', [], null, false);
    check($failedDatabase['status'] === 503, 'Database outage did not return 503.');
    check(!str_contains($failedDatabase['body'], 'Isolated-Test-Passphrase-2026')
        && !str_contains($failedDatabase['body'], 'PDOException')
        && !str_contains($failedDatabase['body'], 'magirc_test'), 'Database outage exposed internal details.');

    echo "Isolated HTTP runtime and security checks: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Isolated HTTP check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
