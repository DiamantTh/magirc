<?php

require_once(__DIR__ . '/Security.class.php');

/**
 * Reads MagIRC database settings from JSON while retaining read-only support
 * for the PHP configuration files used by existing installations.
 */
class MagircConfigStore
{
    private static $defaults = [
        'magirc' => [
            'username' => '', 'password' => '', 'database' => '', 'hostname' => 'localhost',
            'port' => 3306, 'ssl' => false, 'ssl_key' => null, 'ssl_cert' => null, 'ssl_ca' => null
        ],
        'anope' => [
            'username' => 'anope', 'password' => 'anope', 'database' => 'anope', 'hostname' => 'localhost',
            'port' => 3306, 'ssl' => false, 'ssl_key' => null, 'ssl_cert' => null, 'ssl_ca' => null,
            'prefix' => 'anope_'
        ],
        'denora' => [
            'username' => 'denora', 'password' => 'denora', 'database' => 'denora', 'hostname' => 'localhost',
            'port' => 3306, 'ssl' => false, 'ssl_key' => null, 'ssl_cert' => null, 'ssl_ca' => null,
            'current' => 'current', 'maxvalues' => 'maxvalues', 'user' => 'user', 'server' => 'server',
            'stats' => 'stats', 'channelstats' => 'channelstats', 'serverstats' => 'serverstats',
            'ustats' => 'ustats', 'cstats' => 'cstats', 'chan' => 'chan', 'ison' => 'ison', 'aliases' => 'aliases'
        ]
    ];

    public static function defaults($name)
    {
        self::assertName($name);
        return self::$defaults[$name];
    }

    public static function path($name, $directory)
    {
        self::assertName($name);
        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . '.json';
    }

    public static function load($name, $directory)
    {
        self::assertName($name);
        $jsonFile = self::path($name, $directory);
        if (is_file($jsonFile)) {
            $contents = file_get_contents($jsonFile);
            $config = json_decode($contents, true);
            if (!is_array($config) || json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Configuration file is invalid.');
            }
            return self::validate($name, $config);
        }

        $legacyFile = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . '.cfg.php';
        if (is_file($legacyFile)) {
            $config = self::readLegacyConfig($legacyFile);
            if (!is_array($config)) {
                throw new RuntimeException('Configuration file is invalid.');
            }
            return self::validate($name, $config);
        }

        throw new RuntimeException('Configuration file is missing.');
    }

    public static function save($name, $directory, array $config)
    {
        $validated = self::validate($name, $config);
        $json = json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Configuration could not be encoded.');
        }

        $target = self::path($name, $directory);
        $temporary = $target . '.' . bin2hex(MagircSecurity::randomBytes(8)) . '.tmp';
        $previousUmask = umask(0077);
        $handle = @fopen($temporary, 'xb');
        umask($previousUmask);
        if ($handle === false) {
            throw new RuntimeException('Configuration could not be written.');
        }

        try {
            if (fwrite($handle, $json . "\n") === false || !fflush($handle)) {
                throw new RuntimeException('Configuration could not be written.');
            }
        } catch (Exception $exception) {
            fclose($handle);
            @unlink($temporary);
            throw $exception;
        }
        fclose($handle);
        @chmod($temporary, 0600);
        if (!@rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException('Configuration could not be written.');
        }
        return true;
    }

    public static function validate($name, array $config)
    {
        self::assertName($name);
        $validated = array_replace(self::$defaults[$name], $config);
        $allowed = array_keys(self::$defaults[$name]);
        foreach (array_keys($config) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Configuration contains an unsupported field.');
            }
        }

        foreach (['username', 'password', 'ssl_key', 'ssl_cert', 'ssl_ca'] as $key) {
            if (!is_string($validated[$key]) && $validated[$key] !== null) {
                throw new InvalidArgumentException('Configuration value is invalid.');
            }
            if (is_string($validated[$key]) && (strlen($validated[$key]) > 1024 || str_contains($validated[$key], "\0"))) {
                throw new InvalidArgumentException('Configuration value is invalid.');
            }
        }
        if (!is_string($validated['database']) || !preg_match('/^[A-Za-z0-9_$.-]+$/D', $validated['database'])) {
            throw new InvalidArgumentException('Database name is invalid.');
        }
        if (!is_string($validated['hostname']) || !preg_match('/^[A-Za-z0-9._:-]+$/D', $validated['hostname'])) {
            throw new InvalidArgumentException('Database host is invalid.');
        }
        if (is_string($validated['port']) && ctype_digit($validated['port'])) {
            $validated['port'] = (int) $validated['port'];
        }
        if (!is_int($validated['port']) || $validated['port'] < 1 || $validated['port'] > 65535) {
            throw new InvalidArgumentException('Database port is invalid.');
        }
        $validated['ssl'] = self::booleanValue($validated['ssl']);

        foreach (['prefix', 'current', 'maxvalues', 'user', 'server', 'stats', 'channelstats', 'serverstats', 'ustats', 'cstats', 'chan', 'ison', 'aliases'] as $key) {
            if (array_key_exists($key, $validated) && (!is_string($validated[$key]) || !preg_match('/^[A-Za-z0-9_-]*$/D', $validated[$key]))) {
                throw new InvalidArgumentException('Database table name is invalid.');
            }
        }
        return $validated;
    }

    /** Parse only the old $db array assignments; never execute legacy PHP. */
    private static function readLegacyConfig($file)
    {
        $source = file_get_contents($file);
        if (!is_string($source) || strlen($source) > 65536) {
            throw new RuntimeException('Legacy configuration file is invalid.');
        }

        $tokens = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if ($token[0] === T_INLINE_HTML && trim($token[1]) === '') {
                    continue;
                }
                $tokens[] = [$token[0], $token[1]];
            } else {
                $tokens[] = [null, $token];
            }
        }

        $position = 0;
        $config = [];
        while ($position < count($tokens)) {
            self::expectLegacyToken($tokens, $position, T_VARIABLE, '$db');
            if (self::legacyTokenText($tokens, $position) === '=') {
                $position++;
                $config = self::parseLegacyArray($tokens, $position);
            } elseif (self::legacyTokenText($tokens, $position) === '[') {
                $position++;
                $key = self::parseLegacyScalar($tokens, $position);
                if (!is_string($key)) {
                    throw new RuntimeException('Legacy configuration key is invalid.');
                }
                self::expectLegacyToken($tokens, $position, null, ']');
                self::expectLegacyToken($tokens, $position, null, '=');
                $config[$key] = self::parseLegacyScalar($tokens, $position);
            } else {
                throw new RuntimeException('Legacy configuration syntax is invalid.');
            }
            self::expectLegacyToken($tokens, $position, null, ';');
        }
        return $config;
    }

    private static function parseLegacyArray(array $tokens, &$position)
    {
        $opening = self::legacyTokenText($tokens, $position);
        if (isset($tokens[$position]) && $tokens[$position][0] === T_ARRAY) {
            $position++;
            self::expectLegacyToken($tokens, $position, null, '(');
            $closing = ')';
        } elseif ($opening === '[') {
            $position++;
            $closing = ']';
        } else {
            throw new RuntimeException('Legacy configuration array is invalid.');
        }

        $array = [];
        while (self::legacyTokenText($tokens, $position) !== $closing) {
            if ($position >= count($tokens)) {
                throw new RuntimeException('Legacy configuration array is incomplete.');
            }
            $key = self::parseLegacyScalar($tokens, $position);
            self::expectLegacyToken($tokens, $position, T_DOUBLE_ARROW, '=>');
            $array[$key] = self::parseLegacyScalar($tokens, $position);
            if (self::legacyTokenText($tokens, $position) === ',') {
                $position++;
            } elseif (self::legacyTokenText($tokens, $position) !== $closing) {
                throw new RuntimeException('Legacy configuration array separator is invalid.');
            }
        }
        $position++;
        return $array;
    }

    private static function parseLegacyScalar(array $tokens, &$position)
    {
        if (!isset($tokens[$position])) {
            throw new RuntimeException('Legacy configuration value is missing.');
        }
        $token = $tokens[$position++];
        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $quote = $token[1][0];
            $value = substr($token[1], 1, -1);
            if ($quote === "'") {
                return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
            }
            return stripcslashes($value);
        }
        if ($token[0] === T_LNUMBER || $token[0] === T_DNUMBER) {
            return 0 + $token[1];
        }
        if ($token[0] === T_STRING) {
            switch (strtolower($token[1])) {
                case 'true': return true;
                case 'false': return false;
                case 'null': return null;
            }
        }
        throw new RuntimeException('Legacy configuration value is unsupported.');
    }

    private static function expectLegacyToken(array $tokens, &$position, $id, $text)
    {
        if (!isset($tokens[$position]) || $tokens[$position][0] !== $id || $tokens[$position][1] !== $text) {
            throw new RuntimeException('Legacy configuration syntax is invalid.');
        }
        $position++;
    }

    private static function legacyTokenText(array $tokens, $position)
    {
        return isset($tokens[$position]) ? $tokens[$position][1] : null;
    }

    public static function dsn(array $config)
    {
        return sprintf('mysql:dbname=%s;host=%s;port=%d;charset=utf8mb4', $config['database'], $config['hostname'], $config['port']);
    }

    public static function pdoOptions(array $config)
    {
        $mysqlAttributes = class_exists(\Pdo\Mysql::class) ? [
            'init' => \Pdo\Mysql::ATTR_INIT_COMMAND,
            'ssl_key' => \Pdo\Mysql::ATTR_SSL_KEY,
            'ssl_cert' => \Pdo\Mysql::ATTR_SSL_CERT,
            'ssl_ca' => \Pdo\Mysql::ATTR_SSL_CA,
        ] : [
            'init' => PDO::MYSQL_ATTR_INIT_COMMAND,
            'ssl_key' => PDO::MYSQL_ATTR_SSL_KEY,
            'ssl_cert' => PDO::MYSQL_ATTR_SSL_CERT,
            'ssl_ca' => PDO::MYSQL_ATTR_SSL_CA,
        ];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            $mysqlAttributes['init'] => 'SET NAMES utf8mb4',
        ];
        if ($config['ssl']) {
            foreach (['ssl_key' => $mysqlAttributes['ssl_key'], 'ssl_cert' => $mysqlAttributes['ssl_cert'], 'ssl_ca' => $mysqlAttributes['ssl_ca']] as $field => $attribute) {
                if (!empty($config[$field])) {
                    $options[$attribute] = $config[$field];
                }
            }
        }
        return $options;
    }

    private static function booleanValue($value)
    {
        if (in_array($value, [true, 1, '1', 'true'], true)) {
            return true;
        }
        if (in_array($value, [false, 0, '0', 'false', null], true)) {
            return false;
        }
        throw new InvalidArgumentException('Configuration value is invalid.');
    }

    private static function assertName($name)
    {
        if (!isset(self::$defaults[$name])) {
            throw new InvalidArgumentException('Unknown configuration name.');
        }
    }
}
