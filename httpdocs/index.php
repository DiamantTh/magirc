<?php

declare(strict_types=1);

use MagIRC\Bootstrap\Application;
use MagIRC\Bootstrap\ApplicationBootstrap;
use MagIRC\Routes\WebRoutes;
use MagIRC\Security\Security;
use MagIRC\Logging\LoggerFactory;

$projectRoot = dirname(__DIR__);
if (!is_file($projectRoot . '/vendor/autoload.php')) {
    http_response_code(503);
    exit('Please run `composer install` to install application dependencies.');
}
require $projectRoot . '/vendor/autoload.php';

try {
    $paths = ApplicationBootstrap::initialize($projectRoot);
    ApplicationBootstrap::assertRuntime($paths);
    ApplicationBootstrap::assertConfigured($paths);
    Security::startSession();

    $application = new Application(true, $paths);
    WebRoutes::register($application->slim, $application);
    $application->slim->run();
} catch (Throwable $exception) {
    LoggerFactory::get()->critical('MagIRC application bootstrap or request dispatch failed.', ['exception_class' => $exception::class]);
    if (!headers_sent()) {
        http_response_code(503);
    }
    echo $exception instanceof RuntimeException && $exception->getMessage() === 'MagIRC is not configured.'
        ? 'MagIRC is not configured.'
        : 'Service temporarily unavailable.';
}
