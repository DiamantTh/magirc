<?php

declare(strict_types=1);

namespace MagIRC\Installation;

use MagIRC\Bootstrap\ApplicationPaths;
use MagIRC\Config\ConfigurationStore;
use MagIRC\Database\MagircDatabase;
use MagIRC\Logging\LoggerFactory;
use MagIRC\Security\Security;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Throwable;

/** Registers the installer flow on the shared Slim application. */
final class SetupRoutes
{
    public static function register(App $app, Installer $installer, ApplicationPaths $paths): void
    {
        $handler = function (ServerRequestInterface $request, ResponseInterface $response) use ($installer, $paths): ResponseInterface {
            $post = $request->getParsedBody();
            $post = is_array($post) ? $post : [];

            if ($request->getMethod() === 'POST') {
                $token = isset($post['_csrf']) && is_string($post['_csrf']) ? $post['_csrf'] : '';
                if (!Security::verifyCsrfToken($token)) {
                    $response->getBody()->write('Forbidden.');
                    return $response->withStatus(403)->withHeader('Content-Type', 'text/plain; charset=utf-8');
                }
            }

            $query = $request->getQueryParams();
            $stepValue = $query['step'] ?? '1';
            $step = is_int($stepValue) || (is_string($stepValue) && ctype_digit($stepValue))
                ? (int) $stepValue
                : 0;
            if (!in_array($step, [1, 2, 3, 4], true)) {
                $response->getBody()->write('Not found.');
                return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }

            $setupOverride = getenv('MAGIRC_ALLOW_SETUP') === '1';
            $configurationDirectory = $paths->private('conf');
            $installed = is_file($configurationDirectory . DIRECTORY_SEPARATOR . '.installed');
            $configurationPath = ConfigurationStore::path('magirc', $configurationDirectory);
            $legacyConfigurationPath = $configurationDirectory . DIRECTORY_SEPARATOR . 'magirc.cfg.php';

            if (is_file($configurationPath) || is_file($legacyConfigurationPath)) {
                try {
                    $installer->db = MagircDatabase::getInstance();
                    $admins = $installer->checkAdmins();
                    $hasSchema = $installer->hasSchema();
                    if ($admins === true) {
                        Installer::reconcileInstallationMarker(true, $configurationDirectory);
                        $installed = true;
                    } elseif ($admins === false || $hasSchema === false) {
                        Installer::reconcileInstallationMarker(false, $configurationDirectory);
                        $installed = false;
                    }
                } catch (Throwable $exception) {
                    if (!defined('MAGIRC_SETUP_DB_UNAVAILABLE')) {
                        define('MAGIRC_SETUP_DB_UNAVAILABLE', true);
                    }
                    LoggerFactory::get()->error('MagIRC setup installation-state check failed.', ['exception_class' => $exception::class]);
                }
            }

            if ($installed && !$setupOverride) {
                $response->getBody()->write('Setup is disabled.');
                return $response->withStatus(404)->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }

            $html = new SetupController($installer)->render($step, $post);
            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        };

        $app->map(['GET', 'POST'], '/', $handler);
        $app->map(['GET', 'POST'], '/index.php', $handler);
    }
}
