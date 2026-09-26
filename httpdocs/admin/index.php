<?php

declare(strict_types=1);

use MagIRC\Admin\Admin;
use MagIRC\Admin\AdminRoutes;
use MagIRC\Bootstrap\ApplicationBootstrap;
use MagIRC\Logging\LoggerFactory;
use MagIRC\Security\Security;

$projectRoot = dirname(__DIR__, 2);
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

    $admin = new Admin(null, $paths);
    date_default_timezone_set((string) $admin->cfg->timezone);
    if (!defined('DEBUG')) {
        define('DEBUG', $admin->cfg->debug_mode);
    }
    if (!defined('BASE_URL')) {
        define('BASE_URL', rtrim((string) $admin->cfg->base_url, '/') . '/admin/');
    }
    if ($admin->cfg->db_version < DB_VERSION) {
        http_response_code(503);
        exit('SQL Config Table is missing or out of date. Please run the MagIRC Installer.');
    }
    if ($admin->cfg->debug_mode < 1) {
        ini_set('display_errors', '0');
        error_reporting(E_ERROR);
    }

    AdminRoutes::register($admin->slim, $admin, $paths);
    $admin->slim->run();
} catch (Throwable $exception) {
    LoggerFactory::get()->error('MagIRC admin bootstrap failed.', ['exception_class' => $exception::class]);
    if (!headers_sent()) {
        http_response_code(503);
    }
    echo 'Service temporarily unavailable.';
}
