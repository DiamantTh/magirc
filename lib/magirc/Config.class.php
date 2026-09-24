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
            if (!isset($item['parameter']) || !is_string($item['parameter'])) {
                continue;
            }
            $this->config[$item['parameter']] = self::normalizeValue($item['parameter'], $item['value'] ?? '');
        }
        if (isset($this->config['timezone']) && !date_default_timezone_set($this->config['timezone'])) {
            die("ERROR: Invalid timezone setting.<br/>Please check your configuration.");
        }
    }

    /**
     * Normalize values loaded from the legacy configuration table before they
     * reach HTML, JavaScript or URL contexts. Invalid values fail closed while
     * preserving the string based configuration format used by old installs.
     */
    public static function normalizeValue(string $parameter, mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';
        if (strlen($value) > 1024 || str_contains($value, "\0")) {
            return '';
        }

        if (in_array($parameter, ['base_url', 'service_webchat'], true)) {
            if ($value === '') {
                return '';
            }
            if (preg_match('/[\x00-\x20<>"\']/', $value)) {
                return '';
            }
            $parts = parse_url($value);
            if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                || empty($parts['host'])) {
                return '';
            }
            return $value;
        }

        if ($parameter === 'theme') {
            return preg_match('/^[A-Za-z0-9_-]+$/D', $value) ? $value : 'default';
        }
        if ($parameter === 'service') {
            return in_array($value, ['anope', 'denora'], true) ? $value : '';
        }
        if ($parameter === 'ircd_type') {
            return preg_match('/^[A-Za-z0-9_-]+$/D', $value) ? $value : '';
        }
        if ($parameter === 'welcome_mode') {
            return in_array($value, ['statuspage', 'ownpage', 'disabled'], true) ? $value : 'disabled';
        }
        if ($parameter === 'timezone') {
            return in_array($value, \DateTimeZone::listIdentifiers(), true) ? $value : 'UTC';
        }
        if ($parameter === 'locale') {
            return preg_match('/^[A-Za-z]{2}_[A-Za-z]{2}$/D', $value) ? $value : 'en_US';
        }
        if ($parameter === 'net_roundrobin') {
            return preg_match('/^[A-Za-z0-9.:[\\]-]{0,255}$/D', $value) ? $value : '';
        }
        if (in_array($parameter, ['net_port', 'net_port_ssl'], true)) {
            if ($value === '' && $parameter === 'net_port_ssl') {
                return '';
            }
            return ctype_digit($value) && (int) $value >= 1 && (int) $value <= 65535 ? (string) (int) $value : ($parameter === 'net_port' ? '6667' : '');
        }
        if ($parameter === 'live_interval') {
            return ctype_digit($value) ? (string) min(86400, (int) $value) : '0';
        }
        if ($parameter === 'debug_mode') {
            return in_array($value, ['0', '1', '2'], true) ? $value : '0';
        }
        if (in_array($parameter, ['block_schans', 'block_pchans', 'hide_ulined', 'hide_nickaliases', 'cdn_enable', 'rewrite_enable', 'service_webchat_urlencode', 'service_addthis', 'version_show'], true)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        return $value;
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
