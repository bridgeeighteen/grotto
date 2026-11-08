<?php

declare(strict_types=1);

namespace Grotto\Tests\Http;

use Grotto\Tests\TestCase;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

final class HttpSurfaceTest extends TestCase
{
    public function testInfoEndpointDescribesClientSuppliedKeysWithoutSecrets(): void
    {
        $app = $this->app();

        $response = $app->handle(new ServerRequest('GET', 'https://grotto.test/grotto/info'));
        $body = $this->body($response);
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Grotto', $payload['name']);
        self::assertSame('https://grotto.test', $payload['api_base_url']);
        self::assertTrue($payload['default_system_prompt']['exposed']);
        self::assertArrayHasKey('content', $payload['default_system_prompt']);
        self::assertCount(3, $payload['platforms']);
        self::assertSame('request', $payload['api_key']['source']);
        self::assertFalse($payload['api_key']['stored']);
        self::assertSame(['en', 'zh-CN'], $payload['i18n']['supported']);
        self::assertArrayNotHasKey('configured', $payload['platforms'][0]);
        self::assertStringNotContainsString('dashscope.aliyuncs.com', $body);
        self::assertNotEmpty($response->getHeaderLine('X-Grotto-Request-Id'));
    }

    public function testInfoUsesTrustedForwardedOrigin(): void
    {
        $app = $this->app([
            'GROTTO_PUBLIC_BASE_URL' => '',
            'GROTTO_TRUSTED_PROXIES' => '10.0.0.0/8',
        ]);
        $request = new ServerRequest(
            'GET',
            'http://internal.example/grotto/info',
            [
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'grotto.example.com',
            ],
            null,
            '1.1',
            ['REMOTE_ADDR' => '10.1.2.3']
        );

        $payload = json_decode(
            $this->body($app->handle($request)),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('https://grotto.example.com', $payload['api_base_url']);
        self::assertSame('https://grotto.example.com/ui', $payload['ui_url']);
    }

    public function testInfoCanRedactPrompt(): void
    {
        $app = $this->app([
            'GROTTO_EXPOSE_SYSTEM_PROMPT' => 'false',
        ]);

        $payload = json_decode(
            $this->body($app->handle(new ServerRequest('GET', '/grotto/info'))),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertFalse($payload['default_system_prompt']['exposed']);
        self::assertNull($payload['default_system_prompt']['content']);
        self::assertSame(64, strlen($payload['default_system_prompt']['sha256']));
    }

    public function testHealthIsReadyWithoutAnyServerSideKeys(): void
    {
        $app = $this->app();
        $response = $app->handle(new ServerRequest('GET', '/grotto/health'));
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $payload['status']);
        self::assertTrue($payload['ready']);
        self::assertSame(3, $payload['platforms']['enabled']);
        self::assertSame('client_supplied', $payload['checks']['platform_keys']);
        self::assertSame('not_probed', $payload['checks']['upstream_reachability']);
        self::assertArrayNotHasKey('configured', $payload['platforms']);
    }

    public function testHealthIsDegradedWhenNoPlatformsAreEnabled(): void
    {
        $app = $this->app(['GROTTO_PLATFORMS' => 'not-a-platform']);
        $response = $app->handle(new ServerRequest('GET', '/grotto/health'));
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('degraded', $payload['status']);
        self::assertFalse($payload['ready']);
        self::assertSame(0, $payload['platforms']['enabled']);
    }

    public function testReservedGrottoV1NamespaceAlwaysReturns418(): void
    {
        $app = $this->app();
        $response = $app->handle(new ServerRequest('POST', '/grotto/v1/chat/completions'));
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(418, $response->getStatusCode());
        self::assertSame('teapot', $payload['error']['code']);
        self::assertSame('short-and-stout', $response->getHeaderLine('X-Grotto-Teapot'));
    }

    public function testProxyRequiresAPlatformKey(): void
    {
        $app = $this->app([
            'GROTTO_CORS_ORIGINS' => 'https://ui.example',
        ]);

        $request = new ServerRequest(
            'POST',
            '/bailian/v1/chat/completions',
            ['Content-Type' => 'application/json', 'Origin' => 'https://ui.example'],
            '{"model":"qwen-plus","messages":[{"role":"user","content":"hello"}]}'
        );
        $response = $app->handle($request);
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('missing_api_key', $payload['error']['code']);
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        self::assertSame('https://ui.example', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testNonBearerAuthorizationIsRejected(): void
    {
        $app = $this->app();

        $request = (new ServerRequest('POST', '/bailian/v1/chat/completions'))
            ->withHeader('Authorization', 'Basic Zm9vOmJhcg==')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(\GuzzleHttp\Psr7\Utils::streamFor('{"model":"x","messages":[{"role":"user","content":"hi"}]}'));

        $response = $app->handle($request);
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('missing_api_key', $payload['error']['code']);
    }

    public function testCorsPreflightBypassesTheKeyRequirement(): void
    {
        $app = $this->app([
            'GROTTO_CORS_ORIGINS' => 'https://ui.example',
        ]);

        $request = new ServerRequest('OPTIONS', '/bailian/v1/chat/completions', [
            'Origin' => 'https://ui.example',
            'Access-Control-Request-Method' => 'POST',
        ]);
        $response = $app->handle($request);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('https://ui.example', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertStringContainsString('POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    public function testUnknownOpenAiPathIsAJson404(): void
    {
        $app = $this->app();

        $response = $app->handle(new ServerRequest(
            'POST',
            '/bailian/v1/embeddings',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer platform-key',
            ],
            '{"model":"x","input":"hello"}'
        ));
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('endpoint_not_found', $payload['error']['code']);
    }

    public function testUnsupportedCapabilityReturns501BeforeUpstreamCall(): void
    {
        $app = $this->app();

        $response = $app->handle(new ServerRequest(
            'POST',
            '/modelscope/v1/audio/speech',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer platform-key',
            ],
            '{"model":"tts","input":"hello"}'
        ));
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(501, $response->getStatusCode());
        self::assertSame('modelscope_capability_not_supported', $payload['error']['code']);
    }

    public function testWorkspaceRegionIsValidatedBeforeAnyUpstreamCall(): void
    {
        $app = $this->app([
            'BAILIAN_BASE_URL' => 'https://upstream.test',
        ]);

        $response = $app->handle(new ServerRequest(
            'POST',
            '/bailian/custom/not-a-region/ws_1/v1/chat/completions',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer platform-key',
            ],
            '{"model":"qwen-plus","messages":[{"role":"user","content":"hello"}]}'
        ));
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('bailian_invalid_region', $payload['error']['code']);
    }

    public function testMethodNotAllowedIsJson(): void
    {
        $app = $this->app();
        $response = $app->handle(new ServerRequest('POST', '/grotto/info'));

        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('GET', $response->getHeaderLine('Allow'));
    }

    public function testRateLimitReturnsHeadersAnd429(): void
    {
        $storage = '/tmp/opencode/grotto-tests/rate-' . bin2hex(random_bytes(4));
        $app = $this->app([
            'GROTTO_RATE_LIMIT_ENABLED' => 'true',
            'GROTTO_RATE_LIMIT_MAX' => '1',
            'GROTTO_RATE_LIMIT_WINDOW' => '60',
            'GROTTO_RATE_LIMIT_STORAGE' => $storage,
        ]);
        $request = fn () => new ServerRequest(
            'POST',
            '/modelscope/v1/audio/speech',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer platform-key',
            ],
            '{"model":"tts","input":"hello"}'
        );

        $first = $app->handle($request());
        $second = $app->handle($request());

        self::assertSame(501, $first->getStatusCode());
        self::assertSame('0', $first->getHeaderLine('X-RateLimit-Remaining'));
        self::assertSame(429, $second->getStatusCode());
        self::assertSame('0', $second->getHeaderLine('X-RateLimit-Remaining'));
        self::assertNotSame('', $second->getHeaderLine('Retry-After'));
    }

    public function testUiServesHtmlWithLockedDownCsp(): void
    {
        $app = $this->app();
        $response = $app->handle(new ServerRequest('GET', '/'));
        $body = $this->body($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Grotto', $body);
        self::assertStringContainsString('简体中文', $body);
        self::assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertStringNotContainsString("'unsafe-inline'", $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testUiAssetsIncludeChineseTranslationsAndAreServed(): void
    {
        $app = $this->app();

        $js = $app->handle(new ServerRequest('GET', '/assets/app.js'));
        $css = $app->handle(new ServerRequest('GET', '/assets/styles.css'));
        $icon = $app->handle(new ServerRequest('GET', '/assets/favicon.svg'));

        self::assertSame(200, $js->getStatusCode());
        self::assertStringContainsString('application/javascript', $js->getHeaderLine('Content-Type'));
        self::assertStringContainsString('简体中文', $this->body($js));
        self::assertSame(200, $css->getStatusCode());
        self::assertStringContainsString('text/css', $css->getHeaderLine('Content-Type'));
        self::assertSame(200, $icon->getStatusCode());
        self::assertStringContainsString('image/svg+xml', $icon->getHeaderLine('Content-Type'));
    }

    public function testUiCanBeDisabled(): void
    {
        $app = $this->app(['GROTTO_UI_ENABLED' => 'false']);
        $response = $app->handle(new ServerRequest('GET', '/'));
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('ui_disabled', $payload['error']['code']);
    }

    public function testUnknownAssetIs404(): void
    {
        $app = $this->app();
        $response = $app->handle(new ServerRequest('GET', '/assets/unknown.js'));

        self::assertSame(404, $response->getStatusCode());
    }

    private function body(ResponseInterface $response): string
    {
        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream->getContents();
    }
}
