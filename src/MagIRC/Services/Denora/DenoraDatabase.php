<?php

namespace MagIRC\Services\Denora;

use MagIRC\Config\ConfigurationStore;
use MagIRC\Database\Database;
use MagIRC\Logging\LoggerFactory;

class DenoraDatabase extends Database
{
    private static $instance;
    private static ?array $config = null;

    public static function getConfig(): array
    {
        $configurationDirectory = defined('MAGIRC_CONF_DIR') ? MAGIRC_CONF_DIR : dirname(__DIR__, 4) . '/conf';
        self::$config ??= ConfigurationStore::load('denora', $configurationDirectory);
        return self::$config;
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            try {
                $db = self::getConfig();
            } catch (\Exception $exception) {
                LoggerFactory::get()->error('Denora database configuration could not be loaded.', ['exception_class' => $exception::class]);
                die('<strong>MagIRC</strong> is not properly configured<br />Please configure the Denora database in the <a href="admin/">Admin Panel</a>');
            }
            $dsn = ConfigurationStore::dsn($db);
            $args = ConfigurationStore::pdoOptions($db);
            self::$instance = new Database($dsn, $db['username'], $db['password'], $args);
            self::setTableNames($db);
            if (self::$instance->error) {
                LoggerFactory::get()->error('Denora database is unavailable.');
                die('Service temporarily unavailable.');
            }
        }
        return self::$instance;
    }

    private static function setTableNames($db)
    {
        define('TBL_CURRENT', $db['current'] ?? 'current');
        define('TBL_MAXVALUES', $db['maxvalues'] ?? 'maxvalues');
        define('TBL_USER', $db['user'] ?? 'user');
        define('TBL_SERVER', $db['server'] ?? 'server');
        define('TBL_USERSTATS', $db['stats'] ?? 'stats');
        define('TBL_CHANNELSTATS', $db['channelstats'] ?? 'channelstats');
        define('TBL_SERVERSTATS', $db['serverstats'] ?? 'serverstats');
        define('TBL_USTATS', $db['ustats'] ?? 'ustats');
        define('TBL_CSTATS', $db['cstats'] ?? 'cstats');
        define('TBL_CHAN', $db['chan'] ?? 'chan');
        define('TBL_ISON', $db['ison'] ?? 'ison');
        define('TBL_ALIASES', $db['aliases'] ?? 'aliases');
    }
}
