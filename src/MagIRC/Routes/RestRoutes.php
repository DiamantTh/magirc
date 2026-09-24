<?php

declare(strict_types=1);

namespace MagIRC\Routes;

use Magirc;
use Psr\Http\Message\ResponseInterface;
use Slim\App;

function jsonResponse(ResponseInterface $response, mixed $payload): ResponseInterface
{
    $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
}

final class RestRoutes
{
    public static function register(App $app, Magirc $magirc): void
    {
        $validUserMode = static fn (string $mode): bool => in_array($mode, ['nick', 'stats'], true);
        $safeLimit = static function (mixed $value): int {
            $limit = is_scalar($value) ? filter_var((string) $value, FILTER_VALIDATE_INT) : false;
            return $limit === false ? 10 : max(1, min(100, (int) $limit));
        };
// Route Middleware

        $checkPermission = function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler) use ($magirc, $app) {
            $route = \Slim\Routing\RouteContext::fromRequest($request)->getRoute();
            $channel = $route?->getArgument('chan');
            $result = $magirc->service->checkChannel((string) $channel);
            if ($result === 404 || $result === 403) {
                $status = $result;
                $label = $status === 404 ? 'Not Found' : 'Access Denied';
                $response = $app->getResponseFactory()->createResponse($status);
                return jsonResponse($response, ['error' => "HTTP {$status} {$label}"]);
            }
            return $handler->handle($request);
        };

// Routing definitions

        $app->get('/network/status', fn($req, $res) => jsonResponse($res, $magirc->service->getCurrentStatus()));

        $app->get('/network/max', fn($req, $res) => jsonResponse($res, $magirc->service->getMaxValues()));

        $app->get('/network/clients/percent', fn($req, $res) => jsonResponse($res, $magirc->service->makeClientPieData($magirc->service->getClientStats(), $magirc->service->getUserCount())));

        $app->get('/network/clients', fn($req, $res) => jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getClientStats())));

        $app->get('/network/countries/percent', fn($req, $res) => jsonResponse($res, $magirc->service->makeCountryPieData($magirc->service->getCountryStats(), $magirc->service->getUserCount())));

        $app->get('/network/countries', fn($req, $res) => jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getCountryStats())));

        $app->get('/network/countries/map', fn($req, $res) => jsonResponse($res, $magirc->service->getCountryMap()));

        $app->get('/servers', fn($req, $res) => jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getServerList(), 'server')));

        $app->get('/servers/history', fn($req, $res) => jsonResponse($res, $magirc->service->getServerHistory()));

        $app->get('/servers/{server}', fn($req, $res, $args) => jsonResponse($res, $magirc->service->getServer($args['server'])));

        $app->get('/servers/{server}/clients/percent', fn($req, $res, $args) => jsonResponse($res, $magirc->service->makeClientPieData($magirc->service->getClientStats('server', $args['server']), $magirc->service->getUserCount('server', $args['server']))));

        $app->get('/servers/{server}/clients', fn($req, $res, $args) => jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getClientStats('server', $args['server']))));

        $app->get('/servers/{server}/countries/percent', fn($req, $res, $args) => jsonResponse($res, $magirc->service->makeCountryPieData($magirc->service->getCountryStats('server', $args['server']), $magirc->service->getUserCount('server', $args['server']))));

        $app->get('/servers/{server}/countries', fn($req, $res, $args) => jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getCountryStats('server', $args['server']))));

        $app->get('/channels', fn($req, $res) => jsonResponse($res, $magirc->service->getChannelList((($req->getQueryParams()['format'] ?? '') === 'datatables'))));

        $app->get('/channels/history', fn($req, $res) => jsonResponse($res, $magirc->service->getChannelHistory()));

        $app->get('/channels/biggest[/{limit}]', function ($req, $res, $args) use ($magirc, $safeLimit) {
            $limit = $safeLimit($args['limit'] ?? 10);
            return jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getChannelBiggest($limit), 'channel'));
        });

        $app->get('/channels/top[/{limit}]', function ($req, $res, $args) use ($magirc, $safeLimit) {
            $limit = $safeLimit($args['limit'] ?? 10);
            return jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getChannelTop($limit), 'channel'));
        });

        $app->get('/channels/activity/{type}', fn($req, $res, $args) => jsonResponse($res, $magirc->service->getChannelGlobalActivity($args['type'], (($req->getQueryParams()['format'] ?? '') === 'datatables'))));

        $app->group('', function (\Slim\Routing\RouteCollectorProxy $group) use ($magirc) {
            $group->get('/channels/{chan}', fn($req, $res, $args) => jsonResponse($res, $magirc->service->getChannel($args['chan'])));

            $group->get('/channels/{chan}/users', fn($req, $res, $args) => jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getChannelUsers($args['chan']), 'nickname')));

            $group->get('/channels/{chan}/activity/{type}', fn($req, $res, $args) => jsonResponse($res, $magirc->service->getChannelActivity($args['chan'], $args['type'], (($req->getQueryParams()['format'] ?? '') === 'datatables'))));

            $group->get('/channels/{chan}/hourly/{type}', fn($req, $res, $args) => jsonResponse($res, $magirc->service->getChannelHourlyActivity($args['chan'], $args['type'])));

            $group->get('/channels/{chan}/checkstats', fn($req, $res, $args) => jsonResponse($res, $magirc->service->checkChannelStats($args['chan'])));

            $group->get('/channels/{chan}/clients/percent', fn($req, $res, $args) => jsonResponse($res, $magirc->service->makeClientPieData($magirc->service->getClientStats('channel', $args['chan']), $magirc->service->getUserCount('channel', $args['chan']))));

            $group->get('/channels/{chan}/clients', fn($req, $res, $args) => jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getClientStats('channel', $args['chan']))));

            $group->get('/channels/{chan}/countries/percent', fn($req, $res, $args) => jsonResponse($res, $magirc->service->makeCountryPieData($magirc->service->getCountryStats('channel', $args['chan']), $magirc->service->getUserCount('channel', $args['chan']))));

            $group->get('/channels/{chan}/countries', fn($req, $res, $args) => jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getCountryStats('channel', $args['chan']))));
        })->add($checkPermission);

        $app->get('/users/history', fn($req, $res) => jsonResponse($res, $magirc->service->getUserHistory()));

        $app->get('/users/top[/{limit}]', function ($req, $res, $args) use ($magirc, $safeLimit) {
            $limit = $safeLimit($args['limit'] ?? 10);
            return jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getUsersTop($limit), 'uname'));
        });

        $app->get('/users/activity/{type}', fn($req, $res, $args) => jsonResponse($res, $magirc->service->getUserGlobalActivity($args['type'], (($req->getQueryParams()['format'] ?? '') === 'datatables'))));

        $app->get('/users/{mode}/{user}', function ($req, $res, $args) use ($magirc, $validUserMode) {
            if (!$validUserMode((string) $args['mode'])) {
                return jsonResponse($res->withStatus(404), ['error' => 'HTTP 404 Not Found']);
            }
            return jsonResponse($res, $magirc->service->getUser($args['mode'], $args['user']));
        });

        $app->get('/users/{mode}/{user}/channels', function ($req, $res, $args) use ($magirc, $validUserMode) {
            if (!$validUserMode((string) $args['mode'])) {
                return jsonResponse($res->withStatus(404), ['error' => 'HTTP 404 Not Found']);
            }
            return jsonResponse($res, $magirc->service->getUserChannels($args['mode'], $args['user']));
        });

        $app->get('/users/{mode}/{user}/activity[/{chan}]', function ($req, $res, $args) use ($magirc, $validUserMode) {
            if (!$validUserMode((string) $args['mode'])) {
                return jsonResponse($res->withStatus(404), ['error' => 'HTTP 404 Not Found']);
            }
            return jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getUserActivity($args['mode'], $args['user'], $args['chan'] ?? null)));
        });

        $app->get('/users/{mode}/{user}/hourly/{type}', function ($req, $res, $args) use ($magirc, $validUserMode) {
            if (!$validUserMode((string) $args['mode'])) {
                return jsonResponse($res->withStatus(404), ['error' => 'HTTP 404 Not Found']);
            }
            return jsonResponse($res, $magirc->service->getUserHourlyActivity($args['mode'], $args['user'], null, $args['type']));
        });

        $app->get('/users/{mode}/{user}/hourly/{chan}/{type}', function ($req, $res, $args) use ($magirc, $validUserMode) {
            if (!$validUserMode((string) $args['mode'])) {
                return jsonResponse($res->withStatus(404), ['error' => 'HTTP 404 Not Found']);
            }
            return jsonResponse($res, $magirc->service->getUserHourlyActivity($args['mode'], $args['user'], $args['chan'], $args['type']));
        });

        $app->get('/users/{mode}/{user}/checkstats', function ($req, $res, $args) use ($magirc, $validUserMode) {
            if (!$validUserMode((string) $args['mode'])) {
                return jsonResponse($res->withStatus(404), ['error' => 'HTTP 404 Not Found']);
            }
            return jsonResponse($res, $magirc->service->checkUserStats($args['user'], $args['mode']));
        });

        $app->get('/operators', fn($req, $res) => jsonResponse($res, $magirc->arrayForDataTables($magirc->service->getOperatorList(), 'nickname')));
    }
}
