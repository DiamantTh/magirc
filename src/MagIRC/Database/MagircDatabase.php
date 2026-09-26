<?php

namespace MagIRC\Database;

use RuntimeException;
use MagIRC\Config\ConfigurationStore;

class MagircDatabase extends Database
{
    private static $instance;

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            $configurationDirectory = defined('MAGIRC_CONF_DIR') ? MAGIRC_CONF_DIR : dirname(__DIR__, 3) . '/conf';
            $db = ConfigurationStore::load('magirc', $configurationDirectory);
            $dsn = ConfigurationStore::dsn($db);
            $args = ConfigurationStore::pdoOptions($db);
            self::$instance = new Database($dsn, $db['username'], $db['password'], $args);
            if (self::$instance->error) {
                throw new RuntimeException('MagIRC database unavailable.');
            }
        }
        return self::$instance;
    }
}
