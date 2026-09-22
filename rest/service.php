<?php

declare(strict_types=1);

/** MagIRC REST API entry point. */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
date_default_timezone_set('UTC');

$root = dirname(__DIR__);
if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(503);
    die('Please run `composer install` to install application dependencies.');
}
require $root . '/vendor/autoload.php';
require_once $root . '/lib/magirc/version.inc.php';

try {
    $magirc = new Magirc(false);
    \MagIRC\Routes\RestRoutes::register($magirc->slim, $magirc);
    $magirc->slim->add(new \MagIRC\Http\PublicStatisticsCacheMiddleware());
    $magirc->slim->run();
} catch (Throwable $exception) {
    \MagIRC\Logging\LoggerFactory::get()->critical('MagIRC REST bootstrap or request dispatch failed.', ['exception_class' => $exception::class]);
    if (!headers_sent()) {
        http_response_code(503);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"HTTP 500 Internal Server Error"}';
}
