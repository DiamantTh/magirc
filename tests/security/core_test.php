<?php
require_once(__DIR__ . '/../../lib/magirc/DB.class.php');
require_once(__DIR__ . '/../../lib/magirc/ConfigStore.class.php');
require_once(__DIR__ . '/../../lib/magirc/Security.class.php');

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$temp = sys_get_temp_dir() . '/magirc-security-' . str_replace('.', '', uniqid('', true));
mkdir($temp, 0700);
try {
    $config = MagircConfigStore::defaults('magirc');
    $config['username'] = 'magirc';
    $config['database'] = 'magirc';
    $config['password'] = "x'; file_put_contents('" . $temp . "/executed', 'bad'); //";
    MagircConfigStore::save('magirc', $temp, $config);
    $loaded = MagircConfigStore::load('magirc', $temp);
    check($loaded['password'] === $config['password'], 'JSON config did not round-trip the password.');
    check((fileperms($temp . '/magirc.json') & 0777) === 0600, 'JSON config file permissions are not restricted.');
    check(!file_exists($temp . '/executed'), 'Configuration input was executed as PHP.');
    check(!file_exists($temp . '/magirc.cfg.php'), 'JSON writes created an executable PHP config.');

    $invalid = $config;
    $invalid['database'] = 'magirc; DROP TABLE magirc_admin';
    $rejected = false;
    try {
        MagircConfigStore::validate('magirc', $invalid);
    } catch (InvalidArgumentException $exception) {
        $rejected = true;
    }
    check($rejected, 'Unsafe database identifiers were accepted.');
    $tableNameConfig = MagircConfigStore::defaults('anope');
    $tableNameConfig['prefix'] = 'anope-';
    check(MagircConfigStore::validate('anope', $tableNameConfig)['prefix'] === 'anope-', 'Safe legacy SQL table name characters were rejected.');
    $tableNameConfig['prefix'] = 'anope`';
    $rejected = false;
    try {
        MagircConfigStore::validate('anope', $tableNameConfig);
    } catch (InvalidArgumentException $exception) {
        $rejected = true;
    }
    check($rejected, 'Unsafe SQL table name characters were accepted.');

    $legacyDir = $temp . '/legacy-fixtures';
    mkdir($legacyDir, 0700);
    foreach (array('magirc', 'anope', 'denora') as $legacyName) {
        copy(__DIR__ . '/../../conf/' . $legacyName . '.cfg.dist.php', $legacyDir . '/' . $legacyName . '.cfg.php');
    }
    check(MagircConfigStore::load('magirc', $legacyDir)['hostname'] === 'localhost', 'Legacy MagIRC config stopped loading.');
    check(MagircConfigStore::load('anope', $legacyDir)['prefix'] === 'anope_', 'Legacy Anope config stopped loading.');
    check(MagircConfigStore::load('denora', $legacyDir)['current'] === 'current', 'Legacy Denora config stopped loading.');
    $legacyMarker = $temp . '/legacy-executed';
    file_put_contents($temp . '/denora.cfg.php', "<?php\n\$db = array('username' => 'denora', 'password' => 'legacy', 'database' => 'denora', 'hostname' => 'localhost');\nfile_put_contents(" . var_export($legacyMarker, true) . ", 'bad');\n");
    $legacyRejected = false;
    try {
        MagircConfigStore::load('denora', $temp);
    } catch (RuntimeException $exception) {
        $legacyRejected = true;
    }
    check($legacyRejected && !file_exists($legacyMarker), 'Legacy PHP config code was executed.');

    $db = (new ReflectionClass('DB'))->newInstanceWithoutConstructor();
    $_GET = array(
        'columns' => array(array('data' => 'channel` DESC; DROP TABLE x --', 'orderable' => 'true')),
        'order' => array(array('column' => '0', 'dir' => 'desc'))
    );
    check($db->datatablesOrdering(array('channel' => 'channel')) === '', 'Unlisted DataTables column was accepted.');
    $_GET['columns'][0]['data'] = 'channel';
    check($db->datatablesOrdering(array('channel' => 'channel')) === 'ORDER BY `channel` DESC', 'Allowed DataTables sort failed.');
    $_GET['columns'][0]['data'] = array('channel');
    check($db->datatablesOrdering(array('channel' => 'channel')) === '', 'Non-scalar DataTables column was accepted.');
    $_GET = array('start' => '-5', 'length' => '-20');
    check($db->datatablesPaging() === 'LIMIT 0, 100', 'Invalid DataTables paging did not receive a safe default.');
    $_GET = array();
    check($db->datatablesPaging() === 'LIMIT 0, 100', 'Missing DataTables paging did not receive a safe default.');

    session_save_path(sys_get_temp_dir());
    session_name('magirc_test_' . str_replace('.', '', uniqid('', true)));
    MagircSecurity::startSession();
    $token = MagircSecurity::csrfToken();
    check(MagircSecurity::verifyCsrfToken($token), 'Valid CSRF token was rejected.');
    check(!MagircSecurity::verifyCsrfToken($token . 'x'), 'Tampered CSRF token was accepted.');
    $cookie = session_get_cookie_params();
    check($cookie['httponly'] === true && $cookie['samesite'] === 'Lax', 'Session cookie lacks HttpOnly or SameSite protections.');
    check(ini_get('session.use_strict_mode') === '1' && ini_get('session.use_only_cookies') === '1', 'Strict cookie-only sessions are disabled.');
    session_destroy();

    ini_set('error_log', $temp . '/php-error.log');
    $disconnected = (new ReflectionClass('DB'))->newInstanceWithoutConstructor();
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdoProperty = new ReflectionProperty('DB', 'pdo');
    $pdoProperty->setAccessible(true);
    $pdoProperty->setValue($disconnected, $pdo);
    ob_start();
    $queryResult = $disconnected->query('SELECT * FROM secret_missing_table');
    $publicOutput = ob_get_clean();
    check($queryResult === false && $publicOutput === '', 'Database failure was rendered to the public response.');

    echo "core security regressions: OK\n";
} finally {
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    @rmdir($temp);
}
