<?php

declare(strict_types=1);

namespace Grotto\Http\Route;

use Grotto\Http\Controller\GrottoHealthController;
use Grotto\Http\Controller\GrottoInfoController;
use Grotto\Http\Controller\GrottoTeapotController;
use Grotto\Http\Controller\ProxyController;
use Grotto\Http\Controller\UiController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

final class ApiRoutes
{
    private const PROXY_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public static function register(App $app): void
    {
        $app->get('/', UiController::class . ':index');
        $app->get('/ui', UiController::class . ':index');
        $app->get('/ui/', UiController::class . ':index');
        $app->get('/assets/{file:.*}', UiController::class . ':asset');
        $app->get('/favicon.ico', UiController::class . ':favicon');

        $app->get('/grotto/info', GrottoInfoController::class);
        $app->get('/grotto/health', GrottoHealthController::class);
        $app->map(self::PROXY_METHODS, '/grotto/v1', GrottoTeapotController::class);
        $app->map(self::PROXY_METHODS, '/grotto/v1/{path:.*}', GrottoTeapotController::class);

        // Register the workspace route before the generic Bailian group. The
        // generic route would otherwise swallow "custom/{region}/...".
        $app->group('/bailian/custom/{region}/{workspaceId}', function (RouteCollectorProxy $group): void {
            $group->map(
                self::PROXY_METHODS,
                '/v1/{path:.*}',
                ProxyController::class . ':bailianCustom'
            );
        });

        $app->group('/bailian', function (RouteCollectorProxy $group): void {
            $group->map(
                self::PROXY_METHODS,
                '/v1/{path:.*}',
                ProxyController::class . ':bailian'
            );
        });

        $app->group('/modelscope', function (RouteCollectorProxy $group): void {
            $group->map(
                self::PROXY_METHODS,
                '/v1/{path:.*}',
                ProxyController::class . ':modelscope'
            );
        });

        $app->group('/volcengine', function (RouteCollectorProxy $group): void {
            $group->map(
                self::PROXY_METHODS,
                '/v1/{path:.*}',
                ProxyController::class . ':volcengine'
            );
        });
    }
}
