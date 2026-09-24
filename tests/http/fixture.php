<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Integration/IsolatedDatabaseGuard.php';

\MagIRCTests\Integration\IsolatedDatabaseGuard::assertSafe();
$mode = $argv[1] ?? '';
$root = realpath($argv[2] ?? '');
if (!in_array($mode, ['setup', 'cleanup'], true) || $root === false
    || !str_starts_with($root, (string) realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR)
    || !is_file($root . '/.magirc-http-fixture') || !is_dir($root . '/conf')) {
    fwrite(STDERR, "Usage: php tests/http/fixture.php setup|cleanup ISOLATED_APP_ROOT\n");
    exit(2);
}

$dsn = (string) getenv('MAGIRC_TEST_DSN');
$user = (string) (getenv('MAGIRC_TEST_DB_USER') ?: 'root');
$password = (string) (getenv('MAGIRC_TEST_DB_PASSWORD') ?: '');
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = 'anope_http_currentusage, magirc_admin, magirc_content, magirc_config';
if ($mode === 'cleanup') {
    $pdo->exec('DROP TABLE IF EXISTS ' . $tables);
    exit(0);
}

$pdo->exec('DROP TABLE IF EXISTS ' . $tables);
define('MAGIRC_CONF_DIR', $root . '/conf');
require $root . '/vendor/autoload.php';
require $root . '/lib/magirc/version.inc.php';

$setup = (new ReflectionClass(Setup::class))->newInstanceWithoutConstructor();
$setup->db = new DB($dsn, $user, $password);
if (!$setup->configDump()) {
    throw new RuntimeException('HTTP fixture could not create the MagIRC schema.');
}
$pdo->exec("UPDATE magirc_config SET value = '#hidden' WHERE parameter = 'hide_chans'");
$pdo->exec('CREATE TABLE anope_http_currentusage (datetime DATETIME NOT NULL, servers INT NOT NULL, channels INT NOT NULL, users INT NOT NULL, operators INT NOT NULL)');
$pdo->exec("INSERT INTO anope_http_currentusage VALUES ('2026-01-01 00:00:00', 2, 3, 12, 1)");

$connection = [];
foreach (explode(';', substr($dsn, strlen('mysql:'))) as $part) {
    [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
    $connection[$key] = $value;
}
$config = [
    'username' => $user,
    'password' => $password,
    'database' => 'magirc_test',
    'hostname' => $connection['host'] ?? '127.0.0.1',
    'port' => (int) ($connection['port'] ?? 3306),
];
MagircConfigStore::save('magirc', $root . '/conf', $config);
MagircConfigStore::save('anope', $root . '/conf', $config + ['prefix' => 'anope_http_']);
if (!$setup->createAdmin('ci-admin', 'Isolated-Test-Passphrase-2026')) {
    throw new RuntimeException('HTTP fixture could not create its test administrator.');
}

echo "Isolated HTTP fixture: OK\n";
