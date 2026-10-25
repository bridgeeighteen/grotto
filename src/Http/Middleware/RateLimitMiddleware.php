<?php

declare(strict_types=1);

namespace Grotto\Http\Middleware;

use Grotto\Config\Config;
use Grotto\Core\Exception\ApiException;
use Grotto\Http\Error\OpenAiErrorResponder;
use Grotto\Support\FileRateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private ?FileRateLimiter $limiter = null;

    /** @var array<string, mixed> */
    private array $settings;

    public function __construct(
        private readonly Config $config,
        private readonly OpenAiErrorResponder $errors,
        private readonly LoggerInterface $logger,
    ) {
        $this->settings = $config->rateLimit();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isProxyPath($request) || !(bool) $this->settings['enabled']) {
            return $handler->handle($request);
        }

        $this->limiter ??= new FileRateLimiter(
            (string) $this->settings['storage'],
            (int) $this->settings['max_requests'],
            (int) $this->settings['window_seconds'],
        );

        $identity = (string) ($request->getAttribute('grotto.client_id') ?? 'anonymous');
        $key = $identity === 'anonymous'
            ? 'ip:' . ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown')
            : 'client:' . $identity;

        try {
            $result = $this->limiter->hit($key);
        } catch (Throwable $exception) {
            $this->logger->error('Rate limiter unavailable; failing open', [
                'exception' => $exception::class,
            ]);

            return $handler->handle($request);
        }

        if (!$result->allowed) {
            $response = $this->errors->fromApiException(new ApiException(
                429,
                'rate_limit_error',
                'rate_limit_exceeded',
                'This API key has exceeded the Grotto request rate. Retry after the reset window.'
            ));
            $response = $response->withHeader('Retry-After', (string) $result->retryAfter);

            return $this->withHeaders($response, $result);
        }

        return $this->withHeaders($handler->handle($request), $result);
    }

    private function isProxyPath(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return str_starts_with($path, '/bailian/')
            || str_starts_with($path, '/modelscope/')
            || str_starts_with($path, '/volcengine/');
    }

    private function withHeaders(ResponseInterface $response, \Grotto\Support\RateLimitResult $result): ResponseInterface
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $result->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $result->remaining)
            ->withHeader('X-RateLimit-Reset', (string) $result->resetAt);
    }
}
