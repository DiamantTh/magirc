<?php

declare(strict_types=1);

use MagIRC\Bootstrap\Application;
use MagIRC\Bootstrap\ApplicationBootstrap;
use MagIRC\Http\PublicStatisticsCacheMiddleware;
use MagIRC\Logging\LoggerFactory;
use MagIRC\Routes\RestRoutes;
use MagIRC\Security\Security;

$projectRoot = dirname(__DIR__, 2);
if (!is_file($projectRoot . '/vendor/autoload.php')) {
    http_response_code(503);
    exit('Please run `composer install` to install application dependencies.');
}
require $projectRoot . '/vendor/autoload.php';

try {
    $paths = ApplicationBootstrap::initialize($projectRoot);
    ApplicationBootstrap::assertRuntime($paths, false);
    if (isset($_COOKIE[session_name()]) && is_string($_COOKIE[session_name()])) {
        Security::startSession();
    }

    $application = new Application(false, $paths);
    RestRoutes::register($application->slim, $application);
    $application->slim->add(new PublicStatisticsCacheMiddleware());
    $application->slim->run();
} catch (Throwable $exception) {
    LoggerFactory::get()->critical('MagIRC REST bootstrap or request dispatch failed.', ['exception_class' => $exception::class]);
    if (!headers_sent()) {
        http_response_code(503);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"HTTP 503 Service Unavailable"}';
}
