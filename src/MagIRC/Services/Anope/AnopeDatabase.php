<?php

namespace MagIRC\Services\Anope;

use MagIRC\Config\ConfigurationStore;
use MagIRC\Database\Database;
use MagIRC\Logging\LoggerFactory;

class AnopeDatabase extends Database
{
    private static $instance;
    private static ?array $config = null;

    public static function getConfig(): array
    {
        $configurationDirectory = defined('MAGIRC_CONF_DIR') ? MAGIRC_CONF_DIR : dirname(__DIR__, 4) . '/conf';
        self::$config ??= ConfigurationStore::load('anope', $configurationDirectory);
        return self::$config;
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            try {
                $db = self::getConfig();
            } catch (\Exception $exception) {
                LoggerFactory::get()->error('Anope database configuration could not be loaded.', ['exception_class' => $exception::class]);
                die('<strong>MagIRC</strong> is not properly configured<br />Please configure the Anope database in the <a href="admin/">Admin Panel</a>');
            }
            $dsn = ConfigurationStore::dsn($db);
            $args = ConfigurationStore::pdoOptions($db);
            self::$instance = new Database($dsn, $db['username'], $db['password'], $args);
            $prefix = $db['prefix'] ?? null;
            self::setTableNames($prefix);
            if (self::$instance->error) {
                LoggerFactory::get()->error('Anope database is unavailable.');
                die('Service temporarily unavailable.');
            }
        }
        return self::$instance;
    }

    private static function setTableNames($prefix)
    {
        define('TBL_CHAN', $prefix . 'chan');
        define('TBL_CHANSTATS', $prefix . 'chanstats');
        define('TBL_ISON', $prefix . 'ison');
        define('TBL_MAXUSERS', $prefix . 'maxusers');
        define('TBL_SERVER', $prefix . 'server');
        define('TBL_USER', $prefix . 'user');
        define('TBL_CURRENTUSAGE', $prefix . 'currentusage');
        define('TBL_MAXUSAGE', $prefix . 'maxusage');
        define('TBL_HISTORY', $prefix . 'history');
    }
}
