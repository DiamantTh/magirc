<?php

declare(strict_types=1);

$baseUrl = $argv[1] ?? '';
if (!preg_match('~^http://127\.0\.0\.1:[0-9]+$~D', $baseUrl)) {
    fwrite(STDERR, "Expected a local test-server URL.\n");
    exit(2);
}

function checkRequest(string $path, string $method, int $expected, string $expectedBody): void
{
    global $baseUrl;
    $context = stream_context_create(['http' => [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 8,
    ]]);
    $body = @file_get_contents($baseUrl . $path, false, $context);
    $status = isset($http_response_header[0]) && preg_match('~^HTTP/\S+ (\d{3})~', $http_response_header[0], $match)
        ? (int) $match[1]
        : 0;
    if ($status !== $expected || !is_string($body) || !str_contains($body, $expectedBody)) {
        throw new RuntimeException("$method $path returned $status; expected $expected and a safe response.");
    }
}

try {
    checkRequest('/index.php/', 'GET', 503, 'MagIRC is not configured');
    checkRequest('/admin/index.php/overview', 'GET', 503, 'MagIRC is not configured');
    checkRequest('/setup/index.php', 'GET', 200, 'Requirements check');
    checkRequest('/setup/index.php', 'POST', 403, 'Forbidden');
    echo "Unconfigured HTTP installer checks: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Unconfigured HTTP check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
