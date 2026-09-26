<?php

declare(strict_types=1);

$docroot = realpath((string) getenv('MAGIRC_TEST_HTTPDOCS'));
if ($docroot === false) {
    http_response_code(500);
    exit;
}
$uriPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$entrypoints = [
    '~^/admin/index\.php(?<pathInfo>/.*)?$~D' => '/admin/index.php',
    '~^/rest/service\.php(?<pathInfo>/.*)?$~D' => '/rest/service.php',
    '~^/setup(?:/index\.php)?(?<pathInfo>/.*)?$~D' => '/setup/index.php',
    '~^/index\.php(?<pathInfo>/.*)?$~D' => '/index.php',
];
foreach ($entrypoints as $pattern => $script) {
    if (preg_match($pattern, $uriPath, $matches)) {
        $_SERVER['SCRIPT_NAME'] = $script;
        $_SERVER['PHP_SELF'] = $script . ($matches['pathInfo'] ?? '');
        $_SERVER['PATH_INFO'] = $matches['pathInfo'] ?? '';
        require $docroot . $script;
        return true;
    }
}
$staticPath = realpath($docroot . $uriPath);
if ($staticPath !== false && str_starts_with($staticPath, $docroot . DIRECTORY_SEPARATOR) && is_file($staticPath)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['PATH_INFO'] = '';
require $docroot . '/index.php';
return true;
