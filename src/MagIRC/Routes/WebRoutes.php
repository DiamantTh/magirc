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
        $themePath = dirname(__DIR__, 3) . '/theme/' . basename((string) $magirc->cfg->theme) . '/tpl';

        $renderError = (static fn($response, int $code) => $view->render($response, 'error.twig', [
            'cfg' => $config,
            'locales' => $locales,
            'err_code' => $code,
        ])->withStatus($code));

        $renderSection = static function ($request, $response, array $args) use ($view, $config, $locales, $themePath, $renderError) {
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

        $app->get('/', static fn($request, $response) => $view->render($response, 'network_main.twig', [
            'cfg' => $config,
            'locales' => $locales,
            'section' => 'network',
        ]))->setName('network');
        $app->get('/network', static fn($request, $response) => $view->render($response, 'network_main.twig', [
            'cfg' => $config,
            'locales' => $locales,
            'section' => 'network',
        ]));

        $app->get('/content/{name}', static function ($request, $response, $args) use ($magirc) {
            $response->getBody()->write((string) $magirc->getContent((string) $args['name']));
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        $app->get('/channel/{target}/{action}', static function ($request, $response, $args) use ($magirc, $view, $config, $locales, $themePath, $renderError) {
            $template = 'channel_' . basename((string) $args['action']) . '.twig';
            if (!is_file($themePath . DIRECTORY_SEPARATOR . $template)) {
                return $renderError($response, 404);
            }
            $status = $magirc->service->checkChannel((string) $args['target']);
            if ($status === 404) {
                return $renderError($response, 404);
            }
            if ($status === 403) {
                return $renderError($response, 405);
            }

            return $view->render($response, $template, [
                'cfg' => $config,
                'locales' => $locales,
                'section' => 'channel',
                'target' => $args['target'],
                'mode' => null,
            ]);
        })->setName('channel');

        $app->get('/user/{target}/{action}', static function ($request, $response, $args) use ($magirc, $view, $config, $locales, $themePath, $renderError) {
            $template = 'user_' . basename((string) $args['action']) . '.twig';
            $parts = explode(':', (string) $args['target'], 2);
            if (!is_file($themePath . DIRECTORY_SEPARATOR . $template) || count($parts) !== 2 || !$magirc->service->checkUser($parts[1], $parts[0])) {
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
        $app->get('/{section}[/{action}]', static fn($request, $response, $args) => $renderSection($request, $response, $args + ['action' => 'main']))->setName('generic');
    }
}
