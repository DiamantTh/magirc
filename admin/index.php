<?php
/**
 * MagIRC - Let the magirc begin!
 * Admin panel
 *
 * @author      Sebastian Vassiliou <h9k@users.noreply.github.com>
 * @copyright   2012 - 2019 Sebastian Vassiliou
 * @link        https://h9k.github.io/magirc/
 * @license     GNU GPL Version 3, see http://www.gnu.org/licenses/gpl-3.0-standalone.html
 * @version     1.7.0
 */

ini_set('display_errors','off');
error_reporting(E_ALL);
ini_set('default_charset','UTF-8');
date_default_timezone_set('UTC');

if (version_compare(PHP_VERSION, '8.4.0', '<')
    || !extension_loaded('pdo')
    || !in_array('mysql', PDO::getAvailableDrivers())
    || !extension_loaded('gettext')
    || !extension_loaded('xml')
    || !extension_loaded('dom')
    || !extension_loaded('mbstring'))
    die('ERROR: System requirements not met. Please run Setup.');
require_once(__DIR__.'/../lib/magirc/ConfigStore.class.php');
require_once(__DIR__.'/../lib/magirc/Security.class.php');
MagircSecurity::sendSecurityHeaders();
$magircConfigPresent = is_file(MagircConfigStore::path('magirc', __DIR__.'/../conf')) || is_file(__DIR__.'/../conf/magirc.cfg.php');
if (!$magircConfigPresent) {
    http_response_code(503);
    die('MagIRC is not configured. Please run Setup.');
}
if (!is_writable(__DIR__ . '/../tmp/'))
    die('ERROR: Unable to write temporary files. Please run Setup.');

function magircAdminTextResponse(\Psr\Http\Message\ResponseInterface $response, int $status, string $message): \Psr\Http\Message\ResponseInterface
{
    $response->getBody()->write($message);
    return $response->withStatus($status)->withHeader('Content-Type', 'text/plain; charset=utf-8');
}

MagircSecurity::startSession();

include_once(__DIR__.'/../lib/magirc/version.inc.php');
if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    die('Please run the `composer install` command to install library dependencies. See README for more information.');
}
require_once(__DIR__.'/../vendor/autoload.php');
if (!is_file(__DIR__.'/../assets/vendor/jquery/jquery.min.js')) {
    http_response_code(503);
    die('Frontend assets are not installed. Run the documented production asset build.');
}

try {
    $admin = new Admin();
} catch (Throwable $exception) {
    \MagIRC\Logging\LoggerFactory::get()->error('MagIRC admin bootstrap failed.', ['exception_class' => $exception::class]);
    http_response_code(503);
    die('Service temporarily unavailable.');
}

date_default_timezone_set($admin->cfg->timezone);
define('DEBUG', $admin->cfg->debug_mode);
define('BASE_URL', $admin->cfg->base_url . '/' . basename(__DIR__) . '/');
if ($admin->cfg->db_version < DB_VERSION)
    die('SQL Config Table is missing or out of date!<br />Please run the <em>MagIRC Installer</em>');
if ($admin->cfg->debug_mode < 1) {
    ini_set('display_errors','off');
    error_reporting(E_ERROR);
}

$admin->slim->post('/login', function($req, $res, $args) use ($admin) {
    $post = (array) $req->getParsedBody();
    if ($admin->login($post['username'] ?? null, $post['password'] ?? null)) {
        return $res->withStatus(303)->withHeader('Location', BASE_URL.'index.php/overview');
    }
    return $res->withStatus(303)->withHeader('Location', BASE_URL);
});
$admin->slim->post('/ajaxlogin', function($req, $res, $args) use ($admin) {
    $post = (array) $req->getParsedBody();
    $success = $admin->login($post['username'] ?? null, $post['password'] ?? null);
    $res->getBody()->write(json_encode($success, JSON_THROW_ON_ERROR));
    return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
});
$admin->slim->post('/logout', function ($req, $res, $args) {
    MagircSecurity::destroySession();
    // Redirect to login screen
    return $res->withStatus(303)->withHeader('Location', BASE_URL);
});

$overviewRoute = function($req, $res, $args = []) use ($admin) {
    if (!$admin->sessionStatus()) {
        return $admin->tpl->render($res, 'login.twig', []);
    }
    return $admin->tpl->render($res, 'overview.twig', [
        'cfg' => $admin->cfg->config,
        'section' => 'overview',
            'setup' => is_dir(__DIR__ . '/../setup'),
        'version' => ['php' => PHP_VERSION, 'slim' => \Composer\InstalledVersions::getPrettyVersion('slim/slim')],
        'username' => $_SESSION['username']
    ]);
};
$admin->slim->get('/', $overviewRoute);
$admin->slim->get('/overview', $overviewRoute);
$admin->slim->get('/configuration/welcome', function($req, $res, $args) use ($admin) {
    if (!$admin->sessionStatus()) {
        return magircAdminTextResponse($res, 403, 'HTTP 403 Access Denied');
    }
    return $admin->tpl->render($res, 'configuration_welcome.twig', [
        'cfg' => $admin->cfg->config,
        'content' => $admin->getContent('welcome')
    ]);
});
$admin->slim->get('/configuration/interface', function($req, $res, $args) use ($admin) {
    if (!$admin->sessionStatus()) {
        return magircAdminTextResponse($res, 403, 'HTTP 403 Access Denied');
    }
    $locales = [];
    foreach (glob(__DIR__ . '/../locale/*') ?: [] as $filename) {
        if (is_dir($filename)) $locales[] = basename($filename);
    }
    $themes = [];
    foreach (glob(__DIR__ . '/../theme/*') ?: [] as $filename) {
        $themes[] = basename($filename);
    }

    return $admin->tpl->render($res, 'configuration_interface.twig', [
        'cfg' => $admin->cfg->config,
        'locales' => $locales,
        'themes' => $themes,
        'timezones' => DateTimeZone::listIdentifiers(),
    ]);
});
$admin->slim->get('/configuration/network', function($req, $res, $args) use ($admin) {
    if (!$admin->sessionStatus()) {
        return magircAdminTextResponse($res, 403, 'HTTP 403 Access Denied');
    }
    $ircds = [];
    foreach (glob(__DIR__ . '/../lib/magirc/ircds/*.inc.php') ?: [] as $filename) {
        if (is_file($filename)) {
            $ircdlist = explode('.', basename($filename));
            $ircds[] = $ircdlist[0];
        }
    }
    return $admin->tpl->render($res, 'configuration_network.twig', [
        'cfg' => $admin->cfg->config,
        'ircds' => $ircds
    ]);
});
$admin->slim->get('/configuration/service/{service}', function($req, $res, $args) use ($admin) {
    if (!$admin->sessionStatus()) {
        return magircAdminTextResponse($res, 403, 'HTTP 403 Access Denied');
    }
    $service = $args['service'] ?? '';
    if (!in_array($service, ['anope', 'denora'], true)) {
        return magircAdminTextResponse($res, 404, 'Not found');
    }
    try {
        $db = MagircConfigStore::load($service, __DIR__.'/../conf');
    } catch (Throwable) {
        $db = MagircConfigStore::defaults($service);
    }
    $db['password'] = '';
    $db_config_file = MagircConfigStore::path($service, __DIR__.'/../conf');

    return $admin->tpl->render($res, 'configuration_service.twig', [
        'cfg' => $admin->cfg->config,
        'db_config_file' => $db_config_file,
        'writable' => is_writable(__DIR__.'/../conf'),
        'db' => $db,
        'service' => $args['service']
    ]);
});
$admin->slim->post('/content', function($req, $res, $args) use ($admin) {
    $post = (array) $req->getParsedBody();
    if (!$admin->sessionStatus()) {
        return magircAdminTextResponse($res, 403, 'HTTP 403 Access Denied');
    }
    foreach ($post as $key => $val) {
        if (!in_array($key, ['csrf_name', 'csrf_value'], true) && is_string($key) && ($key === 'welcome' || preg_match('/^content_[A-Za-z0-9_]+$/D', $key)) && is_string($val)) {
            $admin->saveContent($key, $val);
        }
    }
    $res->getBody()->write(json_encode(true, JSON_THROW_ON_ERROR));
    return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
});
$admin->slim->post('/configuration', function($req, $res, $args) use ($admin) {
    $post = (array) $req->getParsedBody();
    if (!$admin->sessionStatus()) {
        return magircAdminTextResponse($res, 403, 'HTTP 403 Access Denied');
    }
    $success = true;
    foreach ($post as $key => $val) {
        if (in_array($key, ['csrf_name', 'csrf_value'], true) || !is_string($key) || !is_scalar($val)) {
            continue;
        }
        if ($key === 'base_url') {
            $val = (string) $val;
            $val = (str_ends_with($val, "/")) ? substr($val, 0, -1) : $val;
        }
        if (!$admin->saveConfig($key, $val)) {
            $success = false;
        }
    }
    $res->getBody()->write(json_encode($success, JSON_THROW_ON_ERROR));
    return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
});
$admin->slim->post('/configuration/{service}/database', function($req, $res, $args) use ($admin) {
    $post = (array) $req->getParsedBody();
    if (!$admin->sessionStatus()) {
        return magircAdminTextResponse($res, 403, 'HTTP 403 Access Denied');
    }
    $service = $args['service'] ?? '';
    if (!in_array($service, ['anope', 'denora'], true)) {
        return magircAdminTextResponse($res, 404, 'Not found');
    }
    $success = false;
    try {
        $db = MagircConfigStore::load($service, __DIR__.'/../conf');
    } catch (Throwable) {
        $db = MagircConfigStore::defaults($service);
    }
    $fields = array_keys(MagircConfigStore::defaults($service));
    foreach ($fields as $field) {
        if ($field === 'ssl') {
            $db[$field] = isset($post['ssl']);
        } elseif (isset($post[$field]) && is_string($post[$field])) {
            $value = $post[$field];
            if ($field !== 'password') {
                $value = trim($value);
            }
            $db[$field] = ($field === 'password' && $value === '') ? $db[$field] : $value;
        }
    }
    try {
        $success = MagircConfigStore::save($service, __DIR__.'/../conf', $db);
    } catch (Throwable) {
        \MagIRC\Logging\LoggerFactory::get()->warning('MagIRC service database configuration was rejected.');
    }
    $res->getBody()->write(json_encode($success, JSON_THROW_ON_ERROR));
    return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
});
$admin->slim->get('/support/doc/{file}', function($req, $res, $args) use ($admin) {
    if (!$admin->sessionStatus()) {
        return magircAdminTextResponse($res, 403, 'HTTP 403 Access Denied');
    }
    $path = ($args['file'] === 'readme') ? __DIR__ . '/../README.md' : __DIR__ . '/../doc/' . basename($args['file']) . '.md';
    if (is_file($path)) {
        $text = file_get_contents($path);
    } else {
        $text = "ERROR: Specified documentation file not found";
    }
    return $admin->tpl->render($res, 'support_markdown.twig', [
        'cfg' => $admin->cfg->config,
        'text' => $text
    ]);
});
$admin->slim->get('/admin/list', function($req, $res, $args) use ($admin) {
    if (!$admin->sessionStatus()) {
        return magircAdminTextResponse($res, 403, 'HTTP 403 Access Denied');
    }
    $admin->db->query("SELECT username, realname, email FROM magirc_admin", SQL_ALL, SQL_ASSOC);
    $res->getBody()->write(json_encode(['aaData' => $admin->db->record], JSON_THROW_ON_ERROR));
    return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
});
$admin->slim->get('/{section}[/{action}]', function($req, $res, $args) use ($admin) {
    $action = $args['action'] ?? "main";
    if (!$admin->sessionStatus()) {
        return $admin->tpl->render($res, 'login.twig', []);
    }
    $tpl_file = basename($args['section']) . '_' . basename($action) . '.twig';
    $tpl_path = __DIR__ . '/tpl/' . $tpl_file;
    if (file_exists($tpl_path)) {
        return $admin->tpl->render($res, $tpl_file, [
            'cfg' => $admin->cfg->config,
            'section' => $args['section'],
        ]);
    }
    return $admin->tpl->render($res, "error.twig", [
        'cfg' => $admin->cfg->config,
        'err_code' => 404,
    ])->withStatus(404);
});

$admin->slim->run();
