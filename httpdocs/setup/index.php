<?php

declare(strict_types=1);

use MagIRC\Bootstrap\ApplicationBootstrap;
use MagIRC\Bootstrap\SlimApplicationFactory;
use MagIRC\Installation\Installer;
use MagIRC\Installation\SetupRoutes;
use MagIRC\Logging\LoggerFactory;
use MagIRC\Security\Security;

$projectRoot = dirname(__DIR__, 2);
if (!is_file($projectRoot . '/vendor/autoload.php')) {
    http_response_code(503);
    exit('Setup dependencies are not installed.');
}
require $projectRoot . '/vendor/autoload.php';

try {
    $paths = ApplicationBootstrap::initialize($projectRoot);
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    if (!is_dir($paths->private('tmp')) || !is_writable($paths->private('tmp'))) {
        throw new RuntimeException('Setup runtime directory is not writable.');
    }
    if (!is_file($paths->public('assets', 'vendor', 'jquery', 'jquery.min.js'))) {
        throw new RuntimeException('Frontend assets are not installed.');
    }

    Security::startSession();
    $installer = new Installer($paths);
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/setup/index.php');
    $basePath = trim(dirname($scriptName), '/.');
    $app = SlimApplicationFactory::create([], $basePath);
    SetupRoutes::register($app, $installer, $paths);
    $app->run();
} catch (Throwable $exception) {
    LoggerFactory::get()->error('MagIRC setup bootstrap failed.', ['exception_class' => $exception::class]);
    if (!headers_sent()) {
        http_response_code(503);
    }
    echo 'Setup is temporarily unavailable.';
}
