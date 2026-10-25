<?php

declare(strict_types=1);

namespace Grotto\Http\Controller;

use Grotto\Config\Config;
use Grotto\Core\Exception\ApiException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class UiController
{
    private const ASSETS = [
        'app.js' => 'application/javascript; charset=utf-8',
        'styles.css' => 'text/css; charset=utf-8',
        'favicon.svg' => 'image/svg+xml',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function index(): ResponseInterface
    {
        $this->assertEnabled();
        $path = $this->config->root() . '/frontend/index.html';

        if (!is_file($path)) {
            throw ApiException::notFound('The Grotto UI is not installed.', 'ui_not_found');
        }

        return $this->htmlFile($path);
    }

    /**
     * @param array<string, string> $args
     */
    public function asset(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        unset($request, $response);

        return $this->assetFile((string) ($args['file'] ?? ''));
    }

    public function favicon(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        unset($request, $response);

        return $this->assetFile('favicon.svg');
    }

    private function assetFile(string $file): ResponseInterface
    {
        $this->assertEnabled();

        if (!isset(self::ASSETS[$file])) {
            throw ApiException::notFound('The requested UI asset does not exist.', 'asset_not_found');
        }

        $path = $this->config->root() . '/frontend/' . $file;

        if (!is_file($path)) {
            throw ApiException::notFound('The requested UI asset does not exist.', 'asset_not_found');
        }

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', self::ASSETS[$file])
            ->withHeader('Cache-Control', 'public, max-age=300')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withBody($this->streamFactory->createStreamFromFile($path, 'r'));
    }

    private function htmlFile(string $path): ResponseInterface
    {
        $html = file_get_contents($path);

        if ($html === false) {
            throw ApiException::notFound('The Grotto UI is not readable.', 'ui_not_found');
        }

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withHeader('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "style-src 'self' https://fonts.googleapis.com",
                "font-src https://fonts.gstatic.com",
                "script-src 'self'",
                "img-src 'self' data: blob: https:",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'none'",
                "form-action 'self'",
            ]))
            ->withBody($this->streamFactory->createStream($html));
    }

    private function assertEnabled(): void
    {
        if (!$this->config->uiEnabled()) {
            throw ApiException::notFound('The Grotto UI is disabled.', 'ui_disabled');
        }
    }
}
