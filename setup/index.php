<?php
/** MagIRC setup entry point. */

ini_set('display_errors', 'off');
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
date_default_timezone_set('UTC');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');

require_once(__DIR__ . '/../lib/magirc/ConfigStore.class.php');
require_once(__DIR__ . '/../lib/magirc/Security.class.php');
require_once(__DIR__ . '/../lib/magirc/DB.class.php');

define('MAGIRC_CONF_DIR', realpath(__DIR__ . '/../conf'));
define('MAGIRC_CFG_FILE', MagircConfigStore::path('magirc', MAGIRC_CONF_DIR));

$step = isset($_GET['step']) && ctype_digit((string) $_GET['step']) ? (int) $_GET['step'] : 1;
if (!in_array($step, [1, 2, 3, 4], true)) {
    http_response_code(404);
    die('Not found.');
}

MagircSecurity::startSession();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : '';
    if (!MagircSecurity::verifyCsrfToken($token)) {
        http_response_code(403);
        die('Forbidden.');
    }
}

if (!is_writable(__DIR__ . '/../tmp')) {
    http_response_code(503);
    die('Setup is temporarily unavailable.');
}
if (!is_file(__DIR__ . '/../assets/vendor/jquery/jquery.min.js')) {
    http_response_code(503);
    die('Frontend assets are not installed. Run the documented production asset build.');
}
include_once(__DIR__ . '/../lib/magirc/version.inc.php');
if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
    http_response_code(503);
    die('Setup dependencies are not installed.');
}
require __DIR__ . '/../vendor/autoload.php';
require_once(__DIR__ . '/lib/Setup.class.php');

$setupOverride = getenv('MAGIRC_ALLOW_SETUP') === '1';
$installed = is_file(MAGIRC_CONF_DIR . DIRECTORY_SEPARATOR . '.installed');
try {
    $setup = new Setup();
} catch (Throwable $exception) {
    \MagIRC\Logging\LoggerFactory::get()->error('MagIRC setup bootstrap failed.', ['exception_class' => $exception::class]);
    http_response_code(503);
    die('Setup is temporarily unavailable.');
}
$configPresent = is_file(MAGIRC_CFG_FILE) || is_file(__DIR__ . '/../conf/magirc.cfg.php');
if ($configPresent) {
    try {
        $setup->db = Magirc_DB::getInstance();
        $admins = $setup->checkAdmins();
        $hasSchema = $setup->hasSchema();
        if ($admins === true) {
            Setup::reconcileInstallationMarker($admins);
            $installed = true;
        } elseif ($admins === false || $hasSchema === false) {
            // A database without an administrator must remain recoverable so
            // the first administrator can be created safely. A stale marker
            // from an interrupted installation must not disable that path.
            Setup::reconcileInstallationMarker(false);
            $installed = false;
        }
    } catch (Throwable $exception) {
        define('MAGIRC_SETUP_DB_UNAVAILABLE', true);
        \MagIRC\Logging\LoggerFactory::get()->error('MagIRC setup installation-state check failed.', ['exception_class' => $exception::class]);
    }
}

if ($installed && !$setupOverride) {
    http_response_code(404);
    die('Setup is disabled.');
}

switch ($step) {
    case 1:
        include(__DIR__ . '/inc/step1.php');
        break;
    case 2:
        include(__DIR__ . '/inc/step2.php');
        break;
    case 3:
        include(__DIR__ . '/inc/step3.php');
        break;
    case 4:
        include(__DIR__ . '/inc/step4.php');
        break;
}
