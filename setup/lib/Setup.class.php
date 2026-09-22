<?php

require_once(__DIR__ . '/../../lib/magirc/ConfigStore.class.php');
require_once(__DIR__ . '/../../lib/magirc/Security.class.php');

class Setup {
    public $db;
    public $tpl;
    public function __construct() {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__.'/../tpl');
        $this->tpl = new \Twig\Environment($loader, [
            'cache' => __DIR__ . '/../../tmp',
            'debug' => false
        ]);
        $this->tpl->addGlobal('csrf_token', MagircSecurity::csrfToken());

        // We skip db connection in the first steps for check purposes
        if (@$_GET['step'] > 2) {
            $this->db = MagircDB::getInstance();
        }
    }

    /**
     * Makes preliminary requirements checks
     * @return array
     */
    public function requirementsCheck() {
        $status = ['error' => false];

        if (version_compare(phpversion(), '8.4.0', '>=')) {
            $status['php'] = true;
        } else {
            $status['php'] = false;
            $status['error'] = true;
        }

        if (extension_loaded('pdo') == 1 && in_array('mysql', PDO::getAvailableDrivers())) {
            $status['pdo'] = true;
        } else {
            $status['pdo'] = false;
            $status['error'] = true;
        }

        if (extension_loaded('gettext') == 1) {
            $status['gettext'] = true;
        } else {
            $status['gettext'] = false;
            $status['error'] = true;
        }

        if (extension_loaded('xml') == 1) {
            $status['xml'] = true;
        } else {
            $status['xml'] = false;
            $status['error'] = true;
        }

        foreach (['dom', 'mbstring'] as $extension) {
            $status[$extension] = extension_loaded($extension);
            if (!$status[$extension]) {
                $status['error'] = true;
            }
        }

        $status['writable'] = is_dir(MAGIRC_CONF_DIR) && is_writable(MAGIRC_CONF_DIR);

        if (is_writable(__DIR__ . '/../../tmp')) {
            $status['tmp'] = true;
        } else {
            $status['tmp'] = false;
            $status['error'] = true;
        }

        return $status;
    }

    /**
     *  Saves the MagIRC SQL configuration file
     */
    public function saveConfig() {
        if (!isset($_POST['savedb'])) {
            return false;
        }
        try {
            $config = MagircConfigStore::load('magirc', MAGIRC_CONF_DIR);
        } catch (Throwable) {
            $config = MagircConfigStore::defaults('magirc');
        }
        foreach (['username', 'password', 'database', 'hostname', 'ssl_key', 'ssl_cert', 'ssl_ca'] as $field) {
            $value = isset($_POST[$field]) && is_string($_POST[$field]) ? $_POST[$field] : $config[$field];
            if ($field === 'password' && $value === '' && $config[$field] !== '') {
                continue;
            }
            $config[$field] = $field === 'password' ? $value : trim($value);
        }
        $config['port'] = isset($_POST['port']) && is_scalar($_POST['port']) ? (string) $_POST['port'] : (string) $config['port'];
        $config['ssl'] = isset($_POST['ssl']);
        $saved = MagircConfigStore::save('magirc', MAGIRC_CONF_DIR, $config);
        if ($saved && !is_file(MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.installed')) {
            self::markSetupPending();
        }
        return $saved;
    }

    /**
     * Checks if the configuration table is there
     * @return PDOStatement Configuration
     */
    public function configCheck() {
        $query = "SHOW TABLES LIKE 'magirc_config'";
        if (!$this->db->query($query, SQL_INIT)) {
            throw new RuntimeException('Database schema check failed.');
        }
        return $this->db->record;
    }

    /**
     * Gets the Database schema version
     * @return int Version
     */
    private function getDbVersion() {
        $result = $this->db->selectOne('magirc_config', ['parameter' => 'db_version']);
        return $result['value'];
    }

    /**
     * Loads the configuration table schema to the Denora database, for fresh installs
     * @return boolean
     */
    public function configDump() {
        $file_content = file(__DIR__ . '/../sql/schema.sql');
        if ($file_content === false) {
            throw new RuntimeException('MagIRC database schema file is missing.');
        }
        $query = "";
        foreach($file_content as $sql_line) {
            $tsl = trim($sql_line);
            if (($sql_line !== "") && (!str_starts_with($tsl, "--")) && (!str_starts_with($tsl, "#"))) {
                $query .= $sql_line;
                if(preg_match("/;\s*$/", $sql_line)) {
                    $query = str_replace(";", "", "$query");
                    $result = $this->db->query($query);
                    if (!$result) {
                        return false;
                    }
                    $query = "";
                }
            }
        }
        return true;
    }

    /**
     * Generates the base url
     * @return string
     */
    public function generateBaseUrl() {
        $base_url = @$_SERVER['HTTPS'] ? 'https://' : 'http://';
        $base_url .= $_SERVER['SERVER_NAME'];
        $base_url .= $_SERVER['SERVER_PORT'] == 80 ? '' : ':'.$_SERVER['SERVER_PORT'];
        $base_url .= str_replace('setup/index.php', '', $_SERVER['SCRIPT_NAME']);
        return (str_ends_with($base_url, "/")) ? substr($base_url, 0, -1) : $base_url;
    }

    /**
     * Upgrade the MagIRC database
     * @return boolean true: updated, false: no update needed
     */
    public function configUpgrade() {
        $version = $this->getDbVersion();
        $updated = false;
        if ($version != DB_VERSION) {
            if ($version < 2) {
                $this->db->insert('magirc_config', ['parameter' => 'live_interval', 'value' => 15]);
                $this->db->insert('magirc_config', ['parameter' => 'cdn_enable', 'value' => 0]);
            }
            if ($version < 3) {
                $this->db->insert('magirc_config', ['parameter' => 'rewrite_enable', 'value' => 0]);
            }
            if ($version < 4) {
                $this->db->insert('magirc_config', ['parameter' => 'timezone', 'value' => 'UTC']);
            }
            if ($version < 5) {
                $this->db->insert('magirc_config', ['parameter' => 'welcome_mode', 'value' => 'statuspage']);
                $this->db->query("CREATE TABLE IF NOT EXISTS `magirc_content` (
                    `name` varchar(16) NOT NULL default '', `text` text NOT NULL,
                    PRIMARY KEY (`name`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
                $welcome_msg = $this->db->selectOne('magirc_config', ['parameter' => 'msg_welcome']);
                $this->db->insert('magirc_content', ['name' => 'welcome', 'text' => $welcome_msg['value']]);
                $this->db->delete('magirc_config', ['parameter' => 'msg_welcome']);
                $this->db->query("ALTER TABLE `magirc_config` CHANGE `value` `value` VARCHAR( 64 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''");
                $this->db->query("ALTER TABLE `magirc_config` ENGINE = InnoDB");
            }
            if ($version < 6) {
                $this->db->insert('magirc_config', ['parameter' => 'block_spchans', 'value' => 0]);
                $this->db->insert('magirc_config', ['parameter' => 'net_roundrobin', 'value' => '']);
                $this->db->insert('magirc_config', ['parameter' => 'service_adsense_id', 'value' => '']);
                $this->db->insert('magirc_config', ['parameter' => 'service_adsense_channel', 'value' => '']);
                $this->db->insert('magirc_config', ['parameter' => 'service_searchirc', 'value' => '']);
                $this->db->insert('magirc_config', ['parameter' => 'service_netsplit', 'value' => '']);
            }
            if ($version < 7) {
                $this->db->insert('magirc_config', ['parameter' => 'version_show', 'value' => '1']);
            }
            if ($version < 8) {
                $this->db->insert('magirc_config', ['parameter' => 'net_port', 'value' => '6667']);
                $this->db->insert('magirc_config', ['parameter' => 'net_port_ssl', 'value' => '']);
                $roundrobin = $this->db->selectOne('magirc_config', ['parameter' => 'net_roundrobin']);
                if ($roundrobin['value']) {
                    $array = explode(':', $roundrobin['value']);
                    $this->db->update('magirc_config', ['value' => $array[0]], ['parameter' => 'net_roundrobin']);
                    if (count($array) > 1) {
                        $this->db->update('magirc_config', ['value' => $array[1]], ['parameter' => 'net_port']);
                    }
                }
                $this->db->insert('magirc_config', ['parameter' => 'service_webchat', 'value' => '']);
                $this->db->insert('magirc_config', ['parameter' => 'service_mibbit', 'value' => '']);
                $this->db->insert('magirc_config', ['parameter' => 'service_addthis', 'value' => '0']);
            }
            if ($version < 9) {
                $this->db->insert('magirc_config', ['parameter' => 'denora_version', 'value' => '1.4']);
            }
            if ($version < 10) {
                $this->db->query("ALTER TABLE magirc_config CHANGE value value VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''");
            }
            if ($version < 11) {
                $base_url = $this->generateBaseUrl();
                $this->db->insert('magirc_config', ['parameter' => 'base_url', 'value' => $base_url]);
            }
            if ($version < 13) {
                $this->db->insert('magirc_config', ['parameter' => 'service_mibbitid', 'value' => '']);
            }
            if ($version < 14) {
                $this->db->delete('magirc_config', ['parameter' => 'denora_version']);
                $this->db->insert('magirc_config', ['parameter' => 'service', 'value' => 'denora']);
            }
            if ($version < 15) {
                $this->db->insert('magirc_config', ['parameter' => 'hide_nickaliases', 'value' => 0]);
            }
            if ($version < 16) {
                $block_spchans = $this->db->selectOne('magirc_config', ['parameter' => 'block_spchans']);
                $this->db->insert('magirc_config', ['parameter' => 'block_schans', 'value' => $block_spchans['value']]);
                $this->db->insert('magirc_config', ['parameter' => 'block_pchans', 'value' => $block_spchans['value']]);
                $this->db->delete('magirc_config', ['parameter' => 'block_spchans']);
            }
            if ($version < 17) {
                $this->db->delete('magirc_config', ['parameter' => 'service_searchirc']);
            }
            if ($version < 18) {
                $base_url = $this->generateBaseUrl();
                $this->db->update('magirc_config', ['value' => $base_url], ['parameter' => 'base_url']);
            }
            if ($version < 19) {
                $this->db->insert('magirc_config', ['parameter' => 'service_webchat_urlencode', 'value' => 1]);
            }
            $this->db->update('magirc_config', ['value' => DB_VERSION], ['parameter' => 'db_version']);
            $updated = true;
        }
        return $updated;
    }

    /**
     * Checks if there are any admins in the admin table
     * @return boolean true: yes, false: no
     */
    public function checkAdmins() {
        if (!$this->db || !$this->db->query("SELECT id FROM magirc_admin", SQL_INIT)) {
            return null;
        }
        return (bool) $this->db->record;
    }

    public function hasSchema() {
        if (!$this->db || !$this->db->query("SHOW TABLES LIKE 'magirc_config'", SQL_INIT)) {
            return null;
        }
        return (bool) $this->db->record;
    }

    public static function markSetupPending()
    {
        if (is_file(MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.installed')) {
            return false;
        }
        $marker = MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.setup_pending';
        if (is_file($marker)) {
            return true;
        }
        $handle = @fopen($marker, 'x');
        if (!$handle) {
            return is_file($marker);
        }
        fwrite($handle, "Setup in progress\n");
        fclose($handle);
        @chmod($marker, 0600);
        return true;
    }

    public static function markInstalled()
    {
        $marker = MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.installed';
        if (is_file($marker)) {
            @unlink(MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.setup_pending');
            return true;
        }
        $handle = @fopen($marker, 'x');
        if (!$handle) {
            return is_file($marker);
        }
        fwrite($handle, "MagIRC installed\n");
        fclose($handle);
        @chmod($marker, 0600);
        @unlink(MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.setup_pending');
        return true;
    }

    /**
     * Reconcile the installation marker with the verified database state.
     * A missing administrator always keeps setup recoverable; an unknown
     * state (for example a database outage) changes no marker.
     */
    public static function reconcileInstallationMarker(?bool $admins): bool
    {
        if ($admins === true) {
            return self::markInstalled();
        }
        if ($admins === false) {
            @unlink(MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.installed');
            return self::markSetupPending();
        }
        return false;
    }

    public function createAdmin($username, $password)
    {
        if (!is_string($username) || !is_string($password)) {
            return false;
        }
        $username = trim($username);
        if ($username === '' || strlen($username) > 128 || $password === '' || strlen($password) > 4096 || !$this->db) {
            return false;
        }
        if (is_file(MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.installed')) {
            return false;
        }

        $lockFile = MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.installing';
        $lock = @fopen($lockFile, 'x');
        if (!$lock) {
            return false;
        }
        @chmod($lockFile, 0600);
        try {
            if ($this->checkAdmins() !== false) {
                return false;
            }
            $statement = $this->db->prepare('INSERT INTO `magirc_admin` (`username`, `password`) VALUES (:username, :password)');
            $hash = MagircSecurity::hashPassword($password);
            $statement->bindValue(':username', $username, PDO::PARAM_STR);
            $statement->bindValue(':password', $hash, PDO::PARAM_STR);
            if (!$statement->execute()) {
                return false;
            }
            self::markInstalled();
            return true;
        } catch (Throwable $exception) {
            error_log('MagIRC installer failed to create an administrator: ' . $exception->getMessage());
            return false;
        } finally {
            fclose($lock);
            @unlink($lockFile);
        }
    }
}
