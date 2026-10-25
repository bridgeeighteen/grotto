<?php

declare(strict_types=1);

namespace Grotto\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class RequestLogMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = 'req_' . bin2hex(random_bytes(8));
        $started = hrtime(true);
        $request = $request->withAttribute('grotto.request_id', $requestId);

        $response = $handler->handle($request);
        $response = $response->withHeader('X-Grotto-Request-Id', $requestId);

        $path = $request->getUri()->getPath();
        $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);

        if (!$this->shouldLog($path)) {
            return $response;
        }

        $this->logger->info('HTTP request', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $path,
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'platform' => $response->getHeaderLine('X-Grotto-Platform') ?: $this->platformFromPath($path),
            'stream' => str_contains(strtolower($response->getHeaderLine('Content-Type')), 'text/event-stream'),
        ]);

        return $response;
    }

    private function shouldLog(string $path): bool
    {
        foreach (['/assets/', '/grotto/health', '/favicon'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function platformFromPath(string $path): ?string
    {
        foreach (['bailian', 'modelscope', 'volcengine'] as $platform) {
            if (str_starts_with($path, '/' . $platform . '/')) {
                return $platform;
            }
        }

        return null;
    }
}
