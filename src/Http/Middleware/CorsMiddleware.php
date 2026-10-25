<?php

declare(strict_types=1);

namespace Grotto\Http\Middleware;

use Grotto\Config\Config;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $settings = $this->config->cors();
        $allowedOrigin = $this->allowedOrigin($origin, $settings);

        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->responseFactory->createResponse(204);
        } else {
            $response = $handler->handle($request);
        }

        if ($origin !== '') {
            $response = $response->withHeader('Vary', 'Origin');
        }

        if ($allowedOrigin === null) {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', implode(', ', (array) $settings['allowed_headers']))
            ->withHeader('Access-Control-Expose-Headers', implode(', ', (array) $settings['expose_headers']))
            ->withHeader('Access-Control-Max-Age', (string) $settings['max_age']);

        if ((bool) $settings['allow_credentials']) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function allowedOrigin(string $origin, array $settings): ?string
    {
        if ($origin === '') {
            return null;
        }

        $allowed = (array) $settings['allowed_origins'];

        if (in_array('*', $allowed, true)) {
            return (bool) $settings['allow_credentials'] ? $origin : '*';
        }

        return in_array($origin, $allowed, true) ? $origin : null;
    }
}
