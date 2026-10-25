<?php

declare(strict_types=1);

namespace Grotto\Http\Controller;

use Grotto\Config\Config;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class GrottoHealthController
{
    public function __construct(
        private readonly Config $config,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function __invoke(): ResponseInterface
    {
        $platforms = [];
        $enabledCount = 0;

        foreach ($this->config->enabledPlatformIds() as $id) {
            $platform = $this->config->platform($id);
            $enabledCount++;

            $platforms[] = [
                'id' => $id,
                'label' => $platform['short_label'],
                'label_zh' => $platform['short_label_zh'] ?? $platform['short_label'],
                'status' => 'ready',
                'ready' => true,
                'requires_api_key' => true,
                'api_key_source' => 'request',
                'capabilities' => (array) $platform['capabilities'],
            ];
        }

        $ready = $enabledCount > 0;

        $payload = [
            'status' => $ready ? 'ok' : 'degraded',
            'ready' => $ready,
            'time' => gmdate('c'),
            'version' => $this->config->version(),
            'auth' => [
                'required' => true,
                'ready' => true,
                'scheme' => 'Bearer',
                'source' => 'request',
                'stored' => false,
            ],
            'platforms' => [
                'enabled' => $enabledCount,
                'items' => $platforms,
            ],
            'checks' => [
                'process' => 'ok',
                'platform_keys' => 'client_supplied',
                'upstream_reachability' => 'not_probed',
            ],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $body = $body === false ? '{}' : $body;

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream($body));
    }
}
