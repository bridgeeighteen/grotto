<?php

declare(strict_types=1);

namespace Grotto\Core;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Wraps an upstream SSE stream in a PSR-7 response without buffering it.
 *
 * The compatible upstream endpoints already emit OpenAI chat.completion.chunk
 * frames, so the correct transformation is a zero-copy pass-through. Headers
 * that would describe the compressed upstream representation are removed.
 */
final class StreamProxy
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function proxy(ResponseInterface $upstream): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($upstream->getStatusCode());
        $response = $response->withHeader('Content-Type', $upstream->getHeaderLine('Content-Type') ?: 'text/event-stream');
        $response = $response->withHeader('Cache-Control', 'no-cache, no-transform');
        $response = $response->withHeader('X-Accel-Buffering', 'no');
        $response = $response->withHeader('X-Grotto-Upstream-Status', (string) $upstream->getStatusCode());

        /** @var StreamInterface $stream */
        $stream = $upstream->getBody();

        return $response->withBody($stream);
    }
}
