<?php

class Config {

    public $config;

    public function __construct() {
        $this->loadConfig();
    }

    /**
     * Load the configuration and return it
     */
    public function loadConfig() {
        require_once(__DIR__.'/MagircDB.php');
        $db = MagircDB::getInstance();
        $data = $db->selectAll('magirc_config');
        $this->config = [];
        foreach ($data as $item) {
            $this->config[$item['parameter']] = $item['value'];
        }
        if (isset($this->config['timezone']) && !date_default_timezone_set($this->config['timezone'])) {
            die("ERROR: Invalid timezone setting.<br/>Please check your configuration.");
        }
    }

    /**
     * Get the value of the requested parameter
     * @param string $var Parameter
     * @return string Value
     */
    public function __get($var) {
        return $this->config[$var] ?? null;
    }

    /**
     * Set the value to the given parameter
     * @param string $var Parameter
     * @param string $val Value
     */
    public function __set($var, $val) {
        $this->config[$var] = $val;
    }

}
