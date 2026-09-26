<?php

declare(strict_types=1);

namespace MagIRC\Admin;

use Throwable;
use DateTimeZone;
use MagIRC\Bootstrap\ApplicationPaths;
use MagIRC\Config\ConfigurationStore;
use MagIRC\Security\Security;
use Psr\Http\Message\ResponseInterface;
use Slim\App;

function adminTextResponse(ResponseInterface $response, int $status, string $message): ResponseInterface
{
    $response->getBody()->write($message);
    return $response->withStatus($status)->withHeader('Content-Type', 'text/plain; charset=utf-8');
}

final class AdminRoutes
{
    public static function register(App $app, Admin $admin, ApplicationPaths $paths): void
    {
        $app->post('/login', function ($req, $res, $args) use ($admin) {
            $post = (array) $req->getParsedBody();
            if ($admin->login($post['username'] ?? null, $post['password'] ?? null)) {
                return $res->withStatus(303)->withHeader('Location', BASE_URL . 'index.php/overview');
            }
            return $res->withStatus(303)->withHeader('Location', BASE_URL);
        });
        $app->post('/ajaxlogin', function ($req, $res, $args) use ($admin) {
            $post = (array) $req->getParsedBody();
            $success = $admin->login($post['username'] ?? null, $post['password'] ?? null);
            $res->getBody()->write(json_encode($success, JSON_THROW_ON_ERROR));
            return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
        });
        $app->post('/logout', function ($req, $res, $args) {
            Security::destroySession();
            // Redirect to login screen
            return $res->withStatus(303)->withHeader('Location', BASE_URL);
        });

        $overviewRoute = function ($req, $res, $args = []) use ($admin, $paths) {
            if (!$admin->sessionStatus()) {
                return $admin->tpl->render($res, 'login.twig', []);
            }
            return $admin->tpl->render($res, 'overview.twig', [
                'cfg' => $admin->cfg->config,
                'section' => 'overview',
            'setup' => is_dir($paths->public('setup')),
                'version' => ['php' => PHP_VERSION, 'slim' => \Composer\InstalledVersions::getPrettyVersion('slim/slim')],
                'username' => $_SESSION['username']
            ]);
        };
        $app->get('/', $overviewRoute);
        $app->get('/overview', $overviewRoute);
        $app->get('/configuration/welcome', function ($req, $res, $args) use ($admin) {
            if (!$admin->sessionStatus()) {
                return adminTextResponse($res, 403, 'HTTP 403 Access Denied');
            }
            return $admin->tpl->render($res, 'configuration_welcome.twig', [
                'cfg' => $admin->cfg->config,
                'content' => $admin->getContent('welcome')
            ]);
        });
        $app->get('/configuration/interface', function ($req, $res, $args) use ($admin, $paths) {
            if (!$admin->sessionStatus()) {
                return adminTextResponse($res, 403, 'HTTP 403 Access Denied');
            }
            $locales = [];
            foreach (glob($paths->private('locale') . '/*') ?: [] as $filename) {
                if (is_dir($filename)) {
                    $locales[] = basename($filename);
                }
            }
            $themes = [];
            foreach (glob($paths->private('themes') . '/*') ?: [] as $filename) {
                $themes[] = basename($filename);
            }

            return $admin->tpl->render($res, 'configuration_interface.twig', [
                'cfg' => $admin->cfg->config,
                'locales' => $locales,
                'themes' => $themes,
                'timezones' => DateTimeZone::listIdentifiers(),
            ]);
        });
        $app->get('/configuration/network', function ($req, $res, $args) use ($admin, $paths) {
            if (!$admin->sessionStatus()) {
                return adminTextResponse($res, 403, 'HTTP 403 Access Denied');
            }
            $ircds = [];
            foreach (glob($paths->private('src', 'MagIRC', 'Services', 'Ircd') . '/*/Protocol.php') ?: [] as $filename) {
                if (is_file($filename)) {
                    $ircds[] = basename(dirname($filename));
                }
            }
            return $admin->tpl->render($res, 'configuration_network.twig', [
                'cfg' => $admin->cfg->config,
                'ircds' => $ircds
            ]);
        });
        $app->get('/configuration/service/{service}', function ($req, $res, $args) use ($admin, $paths) {
            if (!$admin->sessionStatus()) {
                return adminTextResponse($res, 403, 'HTTP 403 Access Denied');
            }
            $service = $args['service'] ?? '';
            if (!in_array($service, ['anope', 'denora'], true)) {
                return adminTextResponse($res, 404, 'Not found');
            }
            try {
                $db = ConfigurationStore::load($service, $paths->private('conf'));
            } catch (Throwable) {
                $db = ConfigurationStore::defaults($service);
            }
            $db['password'] = '';
            $db_config_file = ConfigurationStore::path($service, $paths->private('conf'));

            return $admin->tpl->render($res, 'configuration_service.twig', [
                'cfg' => $admin->cfg->config,
                'db_config_file' => $db_config_file,
                'writable' => is_writable($paths->private('conf')),
                'db' => $db,
                'service' => $args['service']
            ]);
        });
        $app->post('/content', function ($req, $res, $args) use ($admin) {
            $post = (array) $req->getParsedBody();
            if (!$admin->sessionStatus()) {
                return adminTextResponse($res, 403, 'HTTP 403 Access Denied');
            }
            foreach ($post as $key => $val) {
                if (!in_array($key, ['csrf_name', 'csrf_value'], true) && is_string($key) && ($key === 'welcome' || preg_match('/^content_[A-Za-z0-9_]+$/D', $key)) && is_string($val)) {
                    $admin->saveContent($key, $val);
                }
            }
            $res->getBody()->write(json_encode(true, JSON_THROW_ON_ERROR));
            return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
        });
        $app->post('/configuration', function ($req, $res, $args) use ($admin) {
            $post = (array) $req->getParsedBody();
            if (!$admin->sessionStatus()) {
                return adminTextResponse($res, 403, 'HTTP 403 Access Denied');
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
        $app->post('/configuration/{service}/database', function ($req, $res, $args) use ($admin, $paths) {
            $post = (array) $req->getParsedBody();
            if (!$admin->sessionStatus()) {
                return adminTextResponse($res, 403, 'HTTP 403 Access Denied');
            }
            $service = $args['service'] ?? '';
            if (!in_array($service, ['anope', 'denora'], true)) {
                return adminTextResponse($res, 404, 'Not found');
            }
            $success = false;
            try {
                $db = ConfigurationStore::load($service, $paths->private('conf'));
            } catch (Throwable) {
                $db = ConfigurationStore::defaults($service);
            }
            $fields = array_keys(ConfigurationStore::defaults($service));
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
                $success = ConfigurationStore::save($service, $paths->private('conf'), $db);
            } catch (Throwable) {
                \MagIRC\Logging\LoggerFactory::get()->warning('MagIRC service database configuration was rejected.');
            }
            $res->getBody()->write(json_encode($success, JSON_THROW_ON_ERROR));
            return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
        });
        $app->get('/support/doc/{file}', function ($req, $res, $args) use ($admin, $paths) {
            if (!$admin->sessionStatus()) {
                return adminTextResponse($res, 403, 'HTTP 403 Access Denied');
            }
            $path = ($args['file'] === 'readme') ? $paths->private('README.md') : $paths->private('doc', basename($args['file']) . '.md');
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
        $app->get('/admin/list', function ($req, $res, $args) use ($admin) {
            if (!$admin->sessionStatus()) {
                return adminTextResponse($res, 403, 'HTTP 403 Access Denied');
            }
            $admin->db->query("SELECT username, realname, email FROM magirc_admin", SQL_ALL, SQL_ASSOC);
            $res->getBody()->write(json_encode(['aaData' => $admin->db->record], JSON_THROW_ON_ERROR));
            return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
        });
        $app->get('/{section}[/{action}]', function ($req, $res, $args) use ($admin, $paths) {
            $action = $args['action'] ?? "main";
            if (!$admin->sessionStatus()) {
                return $admin->tpl->render($res, 'login.twig', []);
            }
            $tpl_file = basename($args['section']) . '_' . basename($action) . '.twig';
            $tpl_path = $paths->private('templates', 'admin') . DIRECTORY_SEPARATOR . $tpl_file;
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
    }
}
