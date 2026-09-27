<?php

declare(strict_types=1);

namespace MagIRC\Bootstrap;

use DI\Container;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;

/** Shared Slim, DI, Twig and common middleware initialization. */
final class SlimApplicationFactory
{
    /**
     * @param array<string, mixed> $services
     * @param callable|null $errorHandler Custom area-specific error renderer; receives the Slim app as its final argument.
     */
    public static function create(array $services, string $basePath, ?Twig $view = null, ?callable $errorHandler = null): App
    {
        $container = new Container();
        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }
        if (!$container->has(LoggerInterface::class)) {
            $container->set(LoggerInterface::class, \MagIRC\Logging\LoggerFactory::get());
        }
        if ($view instanceof \Slim\Views\Twig) {
            $container->set(Twig::class, $view);
            $container->set('view', $view);
        }

        AppFactory::setContainer($container);
        $app = AppFactory::create();
        if ($basePath !== '') {
            $app->setBasePath('/' . trim($basePath, '/'));
        }
        if ($view instanceof \Slim\Views\Twig) {
            $app->add(\Slim\Views\TwigMiddleware::create($app, $view));
        }
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();
        $errors = $app->addErrorMiddleware(false, true, true);
        $defaultErrorHandler = $errorHandler === null
            ? function ($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails) use ($app) {
                if ($logErrors) {
                    \MagIRC\Logging\LoggerFactory::get()->error('MagIRC request failed.', ['exception_class' => $exception::class]);
                }
                $status = $exception instanceof \Slim\Exception\HttpNotFoundException ? 404
                    : ($exception instanceof \Slim\Exception\HttpMethodNotAllowedException ? 405 : 500);
                $response = $app->getResponseFactory()->createResponse($status);
                $response->getBody()->write('Service temporarily unavailable.');
                return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }
            : (fn($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails) => $errorHandler($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails, $app));
        $errors->setDefaultErrorHandler($defaultErrorHandler);
        $app->add(new \MagIRC\Http\SecurityHeadersMiddleware());

        return $app;
    }
}
