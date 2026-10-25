<?php

declare(strict_types=1);

namespace Grotto\Http\Controller;

use Grotto\Config\Config;
use Grotto\Core\Exception\ApiException;
use Grotto\Core\ImageSanitizer;
use Grotto\Core\RequestNormalizer;
use Grotto\Core\ResponseNormalizer;
use Grotto\Core\StreamProxy;
use Grotto\Core\SystemPromptInjector;
use Grotto\Core\UpstreamClient;
use Grotto\Core\UpstreamErrorMapper;
use Grotto\Platform\OpenAiResponse;
use Grotto\Platform\PlatformAdapterInterface;
use Grotto\Platform\PlatformCapability;
use Grotto\Platform\PlatformRegistry;
use Grotto\Platform\RequestContext;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class ProxyController
{
    public function __construct(
        private readonly Config $config,
        private readonly PlatformRegistry $platforms,
        private readonly RequestNormalizer $normalizer,
        private readonly ResponseNormalizer $responseNormalizer,
        private readonly SystemPromptInjector $promptInjector,
        private readonly UpstreamClient $upstream,
        private readonly UpstreamErrorMapper $errors,
        private readonly StreamProxy $streamProxy,
        private readonly ImageSanitizer $imageSanitizer,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function bailian(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        unset($response);

        return $this->handle($request, 'bailian', $args, false);
    }

    /**
     * @param array<string, string> $args
     */
    public function bailianCustom(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        unset($response);

        return $this->handle($request, 'bailian', $args, true);
    }

    /**
     * @param array<string, string> $args
     */
    public function modelscope(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        unset($response);

        return $this->handle($request, 'modelscope', $args, false);
    }

    /**
     * @param array<string, string> $args
     */
    public function volcengine(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        unset($response);

        return $this->handle($request, 'volcengine', $args, false);
    }

    /**
     * @param array<string, string> $args
     */
    private function handle(ServerRequestInterface $request, string $platformId, array $args, bool $custom): ResponseInterface
    {
        $path = (string) ($args['path'] ?? '');
        $capability = PlatformCapability::fromPath($path);

        if ($capability === null) {
            throw ApiException::notFound(
                'The requested path is not part of Grotto\'s OpenAI-compatible surface.',
                'endpoint_not_found'
            );
        }

        $adapter = $this->platforms->adapter($platformId);
        $platform = $this->config->platform($platformId);

        if (!$adapter->supports($capability)) {
            throw ApiException::unsupported(
                sprintf(
                    '%s does not support %s on this Grotto instance.',
                    $platform['short_label'],
                    PlatformCapability::label($capability)
                ),
                $platformId . '_capability_not_supported'
            );
        }

        $apiKey = (string) ($request->getAttribute('grotto.platform_key') ?? '');

        if ($apiKey === '') {
            throw ApiException::unauthorized(
                'Missing platform API key. Send the key for the target platform as Authorization: Bearer <key>.',
                'missing_api_key'
            );
        }

        $body = $this->normalizer->normalize($request, $capability);
        $body = $this->promptInjector->apply($capability, $body);

        $stream = $capability === PlatformCapability::CHAT && ($body['stream'] ?? false) === true;
        $contentType = $request->getHeaderLine('Content-Type');
        $rawBody = '';

        if ($capability === PlatformCapability::STT) {
            $rawBody = (string) $request->getBody();
            $contentType = $contentType !== '' ? $contentType : 'multipart/form-data';
        }

        $requestId = (string) ($request->getAttribute('grotto.request_id') ?? 'req_unknown');
        $clientId = (string) ($request->getAttribute('grotto.client_id') ?? 'anonymous');
        $model = isset($body['model']) && is_string($body['model']) ? $body['model'] : null;

        $ctx = new RequestContext(
            platform: $platformId,
            capability: $capability,
            path: $path,
            requestId: $requestId,
            clientId: $clientId,
            stream: $stream,
            region: $custom ? (string) ($args['region'] ?? '') : null,
            workspaceId: $custom ? (string) ($args['workspaceId'] ?? '') : null,
            customEndpoint: $custom,
            contentType: $contentType,
            rawBody: $rawBody,
            model: $model,
            routeArgs: $args,
            apiKey: $apiKey,
        );

        $started = microtime(true);

        try {
            $upstreamRequest = $adapter->toUpstream($capability, $body, $ctx);
            $upstreamResponse = $this->upstream->send($upstreamRequest);
        } catch (ApiException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->errors->fromThrowable($exception, $platformId, $ctx);
        }

        if ($upstreamResponse->getStatusCode() >= 300) {
            throw $this->errors->fromResponse($upstreamResponse, $platformId, $ctx);
        }

        $this->logger->debug('Upstream request completed', [
            'request_id' => $requestId,
            'platform' => $platformId,
            'capability' => $capability,
            'status' => $upstreamResponse->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'stream' => $stream,
        ]);

        if ($stream) {
            return $this->streamProxy->proxy($upstreamResponse)
                ->withHeader('X-Grotto-Platform', $platformId)
                ->withHeader('X-Grotto-Request-Id', $requestId)
                ->withHeader('X-Grotto-Upstream-Status', (string) $upstreamResponse->getStatusCode());
        }

        try {
            $openAiResponse = $adapter->needsAsyncPolling($capability, $upstreamResponse)
                ? $adapter->resolveAsync($capability, $upstreamResponse, $ctx)
                : $adapter->toOpenAi($capability, $upstreamResponse, $ctx);
        } catch (ApiException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->errors->fromThrowable($exception, $platformId, $ctx);
        }

        return $this->toPsrResponse(
            $this->sanitize($openAiResponse, $adapter, $capability),
            $platformId,
            $upstreamResponse->getStatusCode(),
            $requestId
        );
    }

    private function sanitize(
        OpenAiResponse $response,
        PlatformAdapterInterface $adapter,
        string $capability,
    ): OpenAiResponse {
        $response = $this->responseNormalizer->normalize($response, $capability);

        return $this->imageSanitizer->sanitizeResponse(
            $response,
            $adapter->id(),
            $capability,
            $this->logger
        );
    }

    private function toPsrResponse(
        OpenAiResponse $response,
        string $platformId,
        int $upstreamStatus,
        string $requestId,
    ): ResponseInterface {
        $psr = $this->responseFactory->createResponse($response->status);

        foreach (['Content-Type', 'Content-Disposition', 'X-Grotto-Image-Sanitized'] as $header) {
            if (isset($response->headers[$header])) {
                $psr = $psr->withHeader($header, $response->headers[$header]);
            }
        }

        if (!isset($response->headers['Content-Type'])) {
            $psr = $psr->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $body = $response->body instanceof StreamInterface
            ? $response->body
            : $this->streamFactory->createStream($response->body);

        return $psr
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Grotto-Platform', $platformId)
            ->withHeader('X-Grotto-Request-Id', $requestId)
            ->withHeader('X-Grotto-Upstream-Status', (string) $upstreamStatus)
            ->withBody($body);
    }
}
