<?php

declare(strict_types=1);

namespace Grotto\Http\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class GrottoTeapotController
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function __invoke(): ResponseInterface
    {
        $payload = [
            'error' => [
                'message' => 'Grotto is a teapot. This namespace is intentionally reserved.',
                'type' => 'invalid_request_error',
                'code' => 'teapot',
                'param' => null,
            ],
        ];

        return $this->responseFactory->createResponse(418)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Grotto-Teapot', 'short-and-stout')
            ->withBody($this->streamFactory->createStream(
                (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ));
    }
}
