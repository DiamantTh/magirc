<?php

declare(strict_types=1);

/** MagIRC public entry point. */

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
date_default_timezone_set('UTC');

$root = __DIR__;
if (PHP_VERSION_ID < 80400 || !extension_loaded('pdo_mysql') || !extension_loaded('gettext') || !extension_loaded('xml') || !extension_loaded('dom') || !extension_loaded('mbstring')) {
    http_response_code(503);
    die('ERROR: PHP 8.4+, PDO MySQL, gettext, DOM, mbstring and XML are required.');
}

if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(503);
    die('Please run `composer install` to install application dependencies.');
}
require $root . '/vendor/autoload.php';
require_once $root . '/lib/magirc/version.inc.php';
MagircSecurity::sendSecurityHeaders();

if (!is_file(MagircConfigStore::path('magirc', $root . '/conf')) && !is_file($root . '/conf/magirc.cfg.php')) {
    http_response_code(503);
    die('MagIRC is not configured. Please run Setup.');
}
if (!is_writable($root . '/tmp')) {
    http_response_code(503);
    die('Service temporarily unavailable.');
}
if (!is_file($root . '/assets/vendor/jquery/jquery.min.js')) {
    http_response_code(503);
    die('Frontend assets are not installed. Run the documented production asset build.');
}

MagircSecurity::startSession();

try {
    $magirc = new Magirc(true);
    \MagIRC\Routes\WebRoutes::register($magirc->slim, $magirc);
    $magirc->slim->run();
} catch (Throwable $exception) {
    \MagIRC\Logging\LoggerFactory::get()->critical('MagIRC application bootstrap or request dispatch failed.', ['exception_class' => $exception::class]);
    if (!headers_sent()) {
        http_response_code(503);
    }
    echo 'Service temporarily unavailable.';
}
