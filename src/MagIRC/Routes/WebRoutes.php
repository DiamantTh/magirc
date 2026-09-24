<?php

declare(strict_types=1);

namespace MagIRC\Routes;

use Magirc;
use Slim\App;
use Slim\Views\Twig;

final class WebRoutes
{
    public static function register(App $app, Magirc $magirc): void
    {
        /** @var Twig $view */
        $view = $app->getContainer()->get(Twig::class);
        $config = $magirc->cfg->config;
        $locales = $magirc->getLocalesSelect();
        $themeRoot = realpath(dirname(__DIR__, 3) . '/theme');
        $themePath = $themeRoot === false ? false : realpath($themeRoot . DIRECTORY_SEPARATOR . basename((string) $magirc->cfg->theme) . DIRECTORY_SEPARATOR . 'tpl');
        if ($themeRoot === false || $themePath === false || !str_starts_with($themePath, $themeRoot . DIRECTORY_SEPARATOR)) {
            $themePath = $themeRoot === false ? false : realpath($themeRoot . '/default/tpl');
        }
        if ($themePath === false) {
            throw new \RuntimeException('No valid theme template directory is available.');
        }

        $renderError = (fn($response, int $code) => $view->render($response, 'error.twig', [
            'cfg' => $config,
            'locales' => $locales,
            'err_code' => $code,
        ])->withStatus($code));

        $renderSection = function ($request, $response, array $args) use ($view, $config, $locales, $themePath, $renderError) {
            $section = basename((string) ($args['section'] ?? 'network'));
            $action = basename((string) ($args['action'] ?? 'main'));
            $template = $section . '_' . $action . '.twig';
            if (!is_file($themePath . DIRECTORY_SEPARATOR . $template)) {
                return $renderError($response, 404);
            }

            return $view->render($response, $template, [
                'cfg' => $config,
                'locales' => $locales,
                'section' => $section,
                'target' => $args['target'] ?? null,
                'mode' => null,
            ]);
        };

        $app->get('/', fn($request, $response) => $view->render($response, 'network_main.twig', [
            'cfg' => $config,
            'locales' => $locales,
            'section' => 'network',
        ]))->setName('network');
        $app->get('/network', fn($request, $response) => $view->render($response, 'network_main.twig', [
            'cfg' => $config,
            'locales' => $locales,
            'section' => 'network',
        ]));

        $app->get('/content/{name}', function ($request, $response, $args) use ($magirc) {
            if (($args['name'] ?? '') !== 'welcome') {
                return $response->withStatus(404);
            }
            $response->getBody()->write((string) $magirc->getContent('welcome'));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        $app->get('/channel/{target}/{action}', function ($request, $response, $args) use ($magirc, $view, $config, $locales, $themePath, $renderError) {
            $template = 'channel_' . basename((string) $args['action']) . '.twig';
            if (!is_file($themePath . DIRECTORY_SEPARATOR . $template)) {
                return $renderError($response, 404);
            }
            $status = $magirc->service->checkChannel((string) $args['target']);
            if ($status === 404) {
                return $renderError($response, 404);
            }
            if ($status === 403) {
                return $renderError($response, 403);
            }

            return $view->render($response, $template, [
                'cfg' => $config,
                'locales' => $locales,
                'section' => 'channel',
                'target' => $args['target'],
                'mode' => null,
            ]);
        })->setName('channel');

        $app->get('/user/{target}/{action}', function ($request, $response, $args) use ($magirc, $view, $config, $locales, $themePath, $renderError) {
            $template = 'user_' . basename((string) $args['action']) . '.twig';
            $parts = explode(':', (string) $args['target'], 2);
            if (!is_file($themePath . DIRECTORY_SEPARATOR . $template) || count($parts) !== 2 || !in_array($parts[0], ['nick', 'stats'], true) || !$magirc->service->checkUser($parts[1], $parts[0])) {
                return $renderError($response, 404);
            }

            return $view->render($response, $template, [
                'cfg' => $config,
                'locales' => $locales,
                'section' => 'user',
                'target' => $parts[1],
                'mode' => $parts[0],
            ]);
        })->setName('user');

        $app->get('/{section}/{target}/{action}', $renderSection)->setName('genericFull');
        $app->get('/{section}[/{action}]', fn($request, $response, $args) => $renderSection($request, $response, $args + ['action' => 'main']))->setName('generic');
    }
}
