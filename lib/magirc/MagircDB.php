<?php

require_once(__DIR__ . '/ConfigStore.class.php');

class MagircDB extends DB {
    private static $instance;

    public static function getInstance() {
        if (is_null(self::$instance)) {
            $db = MagircConfigStore::load('magirc', PATH_ROOT . 'conf');
            $dsn = MagircConfigStore::dsn($db);
            $args = MagircConfigStore::pdoOptions($db);
            self::$instance = new DB($dsn, $db['username'], $db['password'], $args);
            if (self::$instance->error) throw new RuntimeException('MagIRC database unavailable.');
        }
        return self::$instance;
    }
}
