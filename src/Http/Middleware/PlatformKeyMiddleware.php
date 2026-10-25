<?php

declare(strict_types=1);

namespace Grotto\Http\Middleware;

use Grotto\Core\Exception\ApiException;
use Grotto\Http\Error\OpenAiErrorResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Extracts the target platform's API key from the incoming bearer token.
 *
 * The key is attached to the request for the lifetime of one proxy call. It is
 * never written to configuration, storage, logs, or the rate limiter.
 */
final class PlatformKeyMiddleware implements MiddlewareInterface
{
    private const MAX_KEY_LENGTH = 8192;

    public function __construct(
        private readonly OpenAiErrorResponder $errors,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS' || !$this->isProxyPath($request->getUri()->getPath())) {
            return $handler->handle($request);
        }

        $header = trim($request->getHeaderLine('Authorization'));

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $this->errors->fromApiException(ApiException::unauthorized(
                'Missing platform API key. Send the key for the target platform as Authorization: Bearer <key>.',
                'missing_api_key'
            ));
        }

        $key = trim($matches[1]);

        if ($key === '') {
            return $this->errors->fromApiException(ApiException::unauthorized(
                'The platform API key is empty.',
                'invalid_api_key'
            ));
        }

        if (strlen($key) > self::MAX_KEY_LENGTH || preg_match('/[\x00-\x1F\x7F]/', $key)) {
            return $this->errors->fromApiException(ApiException::unauthorized(
                'The platform API key contains unsupported characters.',
                'invalid_api_key'
            ));
        }

        // A one-way fingerprint lets the rate limiter group requests by the
        // caller's key without keeping the key itself.
        $fingerprint = 'key:' . substr(hash('sha256', $key), 0, 16);

        return $handler->handle(
            $request
                ->withAttribute('grotto.platform_key', $key)
                ->withAttribute('grotto.client_id', $fingerprint)
                ->withAttribute('grotto.api_key_source', 'request')
        );
    }

    private function isProxyPath(string $path): bool
    {
        return str_starts_with($path, '/bailian/')
            || str_starts_with($path, '/modelscope/')
            || str_starts_with($path, '/volcengine/');
    }
}
