<?php

declare(strict_types=1);

use Grotto\Config\Config;
use Grotto\Http\Error\OpenAiErrorResponder;
use Grotto\Platform\PlatformRegistry;
use Grotto\Support\HttpClientFactory;
use Grotto\Support\Logger;
use GuzzleHttp\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

$root = dirname(__DIR__);

return [
    Config::class => static fn (): Config => new Config($root),

    LoggerInterface::class => static fn (Config $config): LoggerInterface => Logger::create($config),

    ClientInterface::class => static fn (Config $config): ClientInterface => HttpClientFactory::create($config),

    ResponseFactoryInterface::class => static fn (): ResponseFactoryInterface => new ResponseFactory(),

    StreamFactoryInterface::class => static fn (): StreamFactoryInterface => new StreamFactory(),

    OpenAiErrorResponder::class => static fn (
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
    ): OpenAiErrorResponder => new OpenAiErrorResponder($responseFactory, $streamFactory),

    PlatformRegistry::class => static fn (ContainerInterface $container): PlatformRegistry => new PlatformRegistry(
        $container->get(Config::class),
        $container
    ),
];
