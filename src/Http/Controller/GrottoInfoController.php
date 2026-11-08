<?php

declare(strict_types=1);

namespace Grotto\Http\Controller;

use Grotto\Config\Config;
use Grotto\Http\RequestOriginResolver;
use Grotto\Platform\PlatformCapability;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class GrottoInfoController
{
    public function __construct(
        private readonly Config $config,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly RequestOriginResolver $originResolver,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $baseUrl = $this->originResolver->resolve($request);
        $prompt = $this->config->systemPrompt();
        $exposed = $this->config->systemPromptExposed();
        $apiKey = $this->config->apiKeyPolicy();
        $platforms = [];

        foreach ($this->config->enabledPlatformIds() as $id) {
            $platform = $this->config->platform($id);
            $capabilityMap = [];

            foreach (PlatformCapability::ALL as $capability) {
                $capabilityMap[$capability] = in_array($capability, (array) $platform['capabilities'], true);
            }

            $platformBase = $baseUrl . $platform['route'] . '/v1';

            $entry = [
                'id' => $id,
                'label' => $platform['label'],
                'label_zh' => $platform['label_zh'] ?? $platform['label'],
                'short_label' => $platform['short_label'],
                'short_label_zh' => $platform['short_label_zh'] ?? $platform['short_label'],
                'route' => $platform['route'],
                'base_url' => $platformBase,
                'requires_api_key' => true,
                'api_key_source' => 'request',
                'capabilities' => $capabilityMap,
                'default_models' => (object) array_intersect_key(
                    (array) $platform['default_models'],
                    array_flip((array) $platform['capabilities'])
                ),
                'notes' => $platform['notes'] ?? null,
            ];

            if (isset($platform['custom_route'])) {
                $entry['custom_base_url_template'] = $baseUrl . $platform['custom_route'] . '/v1';
            }

            $platforms[] = $entry;
        }

        $rate = $this->config->rateLimit();
        $sanitizer = $this->config->imageSanitizer();
        $async = $this->config->async();

        $payload = [
            'name' => $this->config->name(),
            'version' => $this->config->version(),
            'description' => (string) $this->config->get('tagline', ''),
            'api_base_url' => $baseUrl,
            'ui_url' => $baseUrl . '/ui',
            'auth' => [
                'required' => true,
                'ready' => true,
                'scheme' => (string) ($apiKey['scheme'] ?? 'Bearer'),
                'source' => (string) ($apiKey['source'] ?? 'request'),
                'stored' => false,
            ],
            'api_key' => [
                'header' => (string) ($apiKey['header'] ?? 'Authorization'),
                'scheme' => (string) ($apiKey['scheme'] ?? 'Bearer'),
                'source' => 'request',
                'stored' => false,
                'description' => 'Send the API key for the target model platform with every proxy request. Grotto forwards it upstream and never stores it.',
            ],
            'i18n' => [
                'supported' => $this->config->supportedLocales(),
            ],
            'default_system_prompt' => [
                'enabled' => $this->config->systemPromptEnabled(),
                'exposed' => $exposed,
                'content' => $exposed ? $prompt : null,
                'sha256' => hash('sha256', $prompt),
                'length' => mb_strlen($prompt),
            ],
            'image_sanitization' => [
                'enabled' => (bool) ($sanitizer['enabled'] ?? false),
                'scope' => 'inline_base64',
                'formats' => (array) ($sanitizer['formats'] ?? []),
                'url_outputs_sanitized' => false,
            ],
            'rate_limit' => [
                'enabled' => (bool) ($rate['enabled'] ?? false),
                'max_requests' => (int) ($rate['max_requests'] ?? 0),
                'window_seconds' => (int) ($rate['window_seconds'] ?? 0),
            ],
            'async_tasks' => [
                'poll_interval_ms' => (int) $async['poll_interval_ms'],
                'timeout_seconds' => (int) $async['timeout_seconds'],
            ],
            'platforms' => $platforms,
            'endpoints' => [
                'info' => $baseUrl . '/grotto/info',
                'health' => $baseUrl . '/grotto/health',
                'ui' => $baseUrl . '/ui',
            ],
            'configuration_warnings' => $this->config->warnings(),
        ];

        return $this->json($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): ResponseInterface
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $body = $body === false ? '{}' : $body;

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream($body));
    }
}
