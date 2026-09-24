<?php

declare(strict_types=1);

namespace MagIRC\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PublicStatisticsCacheMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sessionActive = session_status() === PHP_SESSION_ACTIVE
            && (isset($_COOKIE[session_name()]) || isset($_SESSION['username']));
        if (strtoupper($request->getMethod()) !== 'GET' || !$this->isPublic($request) || $request->hasHeader('Authorization') || $sessionActive || isset($_SESSION['username'])) {
            return $handler->handle($request)->withHeader('Cache-Control', 'private, no-store');
        }
        $response = $handler->handle($request);
        if ($response->getStatusCode() >= 400) {
            return $response->withHeader('Cache-Control', 'private, no-store');
        }
        $body = (string) $response->getBody();
        $etag = '"' . hash('sha256', $body) . '"';
        if ($request->getHeaderLine('If-None-Match') === $etag) {
            return $response->withStatus(304)->withBody(new \Slim\Psr7\Stream(fopen('php://temp', 'r+')))->withHeader('ETag', $etag)->withHeader('Cache-Control', 'public, max-age=30');
        }
        return $response->withHeader('Cache-Control', 'public, max-age=30')->withHeader('ETag', $etag)->withHeader('Vary', 'Accept-Encoding');
    }

    private function isPublic(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        return (bool) preg_match('#/(?:network/(?:status|max|clients(?:/percent)?|countries(?:/percent|/map)?)|servers(?:/history)?|channels(?:/history)?|operators)$#', $path);
    }
}
