<?php

declare(strict_types=1);

namespace MagIRC\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Csrf\Guard;
use Twig\Environment;

final readonly class CsrfTokenMiddleware implements MiddlewareInterface
{
    public function __construct(private Guard $guard, private Environment $twig)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $this->guard->appendNewTokenToRequest($request);
        $nameKey = $this->guard->getTokenNameKey();
        $valueKey = $this->guard->getTokenValueKey();
        $this->twig->addGlobal('csrf', [
            $nameKey => $request->getAttribute($nameKey),
            $valueKey => $request->getAttribute($valueKey),
        ]);

        return $handler->handle($request);
    }
}
