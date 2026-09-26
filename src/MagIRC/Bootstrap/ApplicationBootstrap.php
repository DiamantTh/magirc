<?php

declare(strict_types=1);

namespace MagIRC\Bootstrap;

use MagIRC\Config\ConfigurationStore;
use MagIRC\Security\Security;

/** Shared process and path initialization for every public entry point. */
final class ApplicationBootstrap
{
    public static function initialize(string $projectRoot): ApplicationPaths
    {
        $paths = new ApplicationPaths($projectRoot);
        ini_set('display_errors', '0');
        error_reporting(E_ALL);
        ini_set('default_charset', 'UTF-8');
        date_default_timezone_set('UTC');

        if (!defined('MAGIRC_CONF_DIR')) {
            define('MAGIRC_CONF_DIR', $paths->private('conf'));
        }
        if (!defined('MAGIRC_CFG_FILE')) {
            define('MAGIRC_CFG_FILE', ConfigurationStore::path('magirc', MAGIRC_CONF_DIR));
        }

        Security::sendSecurityHeaders();
        return $paths;
    }

    public static function assertRuntime(ApplicationPaths $paths, bool $requireFrontendAssets = true): void
    {
        if (
            !extension_loaded('pdo_mysql') || !extension_loaded('gettext') || !extension_loaded('xml')
            || !extension_loaded('dom') || !extension_loaded('mbstring')
        ) {
            throw new \RuntimeException('MagIRC requires PHP 8.4+, PDO MySQL, gettext, DOM, mbstring and XML.');
        }
        if (!is_dir($paths->private('tmp')) || !is_writable($paths->private('tmp'))) {
            throw new \RuntimeException('MagIRC runtime directory is not writable.');
        }
        if ($requireFrontendAssets && !is_file($paths->public('assets', 'vendor', 'jquery', 'jquery.min.js'))) {
            throw new \RuntimeException('MagIRC frontend assets are not installed.');
        }
    }

    public static function assertConfigured(ApplicationPaths $paths): void
    {
        if (
            !is_file(ConfigurationStore::path('magirc', $paths->private('conf')))
            && !is_file($paths->private('conf', 'magirc.cfg.php'))
        ) {
            throw new \RuntimeException('MagIRC is not configured.');
        }
    }
}
