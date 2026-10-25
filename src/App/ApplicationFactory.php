<?php

declare(strict_types=1);

namespace Grotto\App;

use DI\ContainerBuilder;
use Grotto\Config\Config;
use Grotto\Http\Middleware\JsonErrorMiddleware;
use Grotto\Http\Route\ApiRoutes;
use Grotto\Support\Env;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

final class ApplicationFactory
{
    /**
     * @param array<string, string|null>|null $envOverrides
     * @param array<string, mixed> $containerDefinitions Optional testing overrides.
     */
    public static function create(?array $envOverrides = null, array $containerDefinitions = []): App
    {
        $root = dirname(__DIR__, 2);
        Config::loadEnvironment($root);

        if ($envOverrides !== null) {
            Env::override($envOverrides);
        }

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->addDefinitions($root . '/config/container.php');

        if ($containerDefinitions !== []) {
            $builder->addDefinitions($containerDefinitions);
        }

        $container = $builder->build();

        return self::buildApp($container);
    }

    public static function buildApp(ContainerInterface $container): App
    {
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $config = $container->get(Config::class);
        ApiRoutes::register($app);

        // Slim middleware is LIFO. Adding routing first makes every later
        // middleware wrap it. JsonError then sits directly outside routing so
        // CORS and request logging still see error responses.
        $app->addRoutingMiddleware();
        $app->add(JsonErrorMiddleware::class);

        $global = (array) ($config->middleware()['global'] ?? []);

        foreach (array_reverse($global) as $middleware) {
            $app->add($middleware);
        }

        return $app;
    }
}
