<?php

declare(strict_types=1);

namespace Grotto\Platform;

use Grotto\Config\Config;
use Grotto\Core\Exception\ApiException;
use Grotto\Core\UpstreamErrorMapper;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractAdapter implements PlatformAdapterInterface
{
    /** @var array<string, mixed> */
    protected array $platform;

    public function __construct(
        protected readonly Config $config,
        protected readonly ClientInterface $http,
        protected readonly UpstreamErrorMapper $errors,
        protected readonly LoggerInterface $logger,
    ) {
        $this->platform = $config->platform($this->id());
    }

    /**
     * @return array<string, mixed>
     */
    public function platformConfig(): array
    {
        return $this->platform;
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return array_values((array) $this->platform['capabilities']);
    }

    public function needsAsyncPolling(string $capability, ResponseInterface $submission): bool
    {
        return false;
    }

    public function resolveAsync(string $capability, ResponseInterface $submission, RequestContext $ctx): OpenAiResponse
    {
        throw ApiException::unsupported(
            sprintf('Asynchronous %s responses are not supported by %s.', $capability, $this->id()),
            $this->id() . '_async_unsupported'
        );
    }

    public function toOpenAi(string $capability, ResponseInterface $upstreamResponse, RequestContext $ctx): OpenAiResponse
    {
        return $this->passthrough($upstreamResponse);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) $this->platform['base_url'], '/');
    }

    protected function uri(string $path): string
    {
        return $this->baseUrl() . '/' . ltrim($path, '/');
    }

    protected function apiKey(RequestContext $ctx): string
    {
        if ($ctx->apiKey === '') {
            throw ApiException::unauthorized(
                'Missing platform API key. Send the key for the target platform as Authorization: Bearer <key>.',
                'missing_api_key'
            );
        }

        return $ctx->apiKey;
    }

    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    protected function headers(RequestContext $ctx, array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer ' . $this->apiKey($ctx),
            'Accept' => 'application/json',
        ], $extra);
    }

    protected function body(ResponseInterface $response): string
    {
        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $contents = $stream->getContents();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonBody(ResponseInterface $response): array
    {
        $body = $this->body($response);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw ApiException::upstream(
                sprintf('%s returned a response Grotto could not parse.', $this->platform['short_label']),
                $this->id() . '_invalid_upstream_response'
            );
        }

        return $decoded;
    }

    protected function passthrough(ResponseInterface $response): OpenAiResponse
    {
        $headers = [];
        $contentType = $response->getHeaderLine('Content-Type');

        if ($contentType !== '') {
            $headers['Content-Type'] = $contentType;
        }

        $disposition = $response->getHeaderLine('Content-Disposition');
        if ($disposition !== '') {
            $headers['Content-Disposition'] = $disposition;
        }

        if (str_contains(strtolower($contentType), 'json')) {
            return new OpenAiResponse($response->getStatusCode(), $this->body($response), $headers);
        }

        /** @var StreamInterface $stream */
        $stream = $response->getBody();

        if ($contentType === '' && $stream->isSeekable()) {
            $stream->rewind();
            $first = $stream->read(1);
            $stream->rewind();

            if ($first === '{' || $first === '[') {
                $headers['Content-Type'] = 'application/json; charset=utf-8';

                return new OpenAiResponse($response->getStatusCode(), $this->body($response), $headers);
            }
        }

        return new OpenAiResponse($response->getStatusCode(), $stream, $headers);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function logContext(RequestContext $ctx, array $context = []): array
    {
        return array_merge([
            'platform' => $this->id(),
            'capability' => $ctx->capability,
            'request_id' => $ctx->requestId,
        ], $context);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function createdTimestamp(array $payload): int
    {
        return isset($payload['created']) && is_numeric($payload['created'])
            ? (int) $payload['created']
            : time();
    }
}
