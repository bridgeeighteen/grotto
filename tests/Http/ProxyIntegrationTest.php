<?php

declare(strict_types=1);

namespace Grotto\Tests\Http;

use Grotto\App\ApplicationFactory;
use Grotto\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;

final class ProxyIntegrationTest extends TestCase
{
    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    private array $history = [];

    public function testChatIsForwardedWithPromptInjectionAndNormalizedErrorHeaders(): void
    {
        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'chatcmpl-1',
                'object' => 'chat.completion',
                'created' => 123,
                'model' => 'qwen-plus',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'A gateway translates protocols.'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 4, 'total_tokens' => 9],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $app = $this->application($client);
        $request = new ServerRequest(
            'POST',
            'https://grotto.test/bailian/v1/chat/completions',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer test-platform-key',
            ],
            json_encode([
                'model' => 'qwen-plus',
                'messages' => [['role' => 'user', 'content' => 'What is it?']],
            ], JSON_THROW_ON_ERROR)
        );

        $response = $app->handle($request);
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('bailian', $response->getHeaderLine('X-Grotto-Platform'));
        self::assertSame('A gateway translates protocols.', $payload['choices'][0]['message']['content']);
        self::assertCount(1, $this->history);

        $upstreamRequest = $this->history[0]['request'];
        $upstreamBody = json_decode($this->body($upstreamRequest), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            'https://upstream.test/compatible-mode/v1/chat/completions',
            (string) $upstreamRequest->getUri()
        );
        self::assertSame('Bearer test-platform-key', $upstreamRequest->getHeaderLine('Authorization'));
        self::assertSame('system', $upstreamBody['messages'][0]['role']);
        self::assertStringContainsString('You are a helpful assistant', $upstreamBody['messages'][0]['content']);
        self::assertSame('What is it?', $upstreamBody['messages'][1]['content']);
    }

    public function testChatStreamPassesThroughAsSse(): void
    {
        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Hel\"}}]}\n\n"
            . "data: {\"choices\":[{\"delta\":{\"content\":\"lo\"}}]}\n\n"
            . "data: [DONE]\n\n";

        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'text/event-stream'], $sse),
        ]);
        $app = $this->application($client);
        $request = new ServerRequest(
            'POST',
            'https://grotto.test/bailian/v1/chat/completions',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer test-platform-key',
            ],
            json_encode([
                'model' => 'qwen-plus',
                'messages' => [['role' => 'user', 'content' => 'Stream']],
                'stream' => true,
            ], JSON_THROW_ON_ERROR)
        );

        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/event-stream', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-cache, no-transform', $response->getHeaderLine('Cache-Control'));
        self::assertSame($sse, $this->body($response));
        self::assertSame('text/event-stream', $this->history[0]['request']->getHeaderLine('Accept'));
    }

    public function testImageTaskIsPolledAndInlineMetadataIsStripped(): void
    {
        $png = $this->pngWithTextMetadata();
        $dataUrl = 'data:image/png;base64,' . base64_encode($png);

        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'output' => ['task_id' => 'task-1', 'task_status' => 'PENDING'],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'output' => ['task_id' => 'task-1', 'task_status' => 'RUNNING'],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'output' => [
                    'task_id' => 'task-1',
                    'task_status' => 'SUCCEEDED',
                    'results' => [['url' => $dataUrl]],
                ],
                'created' => 456,
            ], JSON_THROW_ON_ERROR)),
        ]);
        $app = $this->application($client);
        $request = new ServerRequest(
            'POST',
            'https://grotto.test/bailian/v1/images/generations',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer test-platform-key',
            ],
            json_encode([
                'model' => 'wanx2.1-t2i-turbo',
                'prompt' => 'A quiet grotto',
            ], JSON_THROW_ON_ERROR)
        );

        $response = $app->handle($request);
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);
        $sanitizedUrl = $payload['data'][0]['url'];
        $sanitizedBinary = base64_decode(substr($sanitizedUrl, strpos($sanitizedUrl, ',') + 1), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('1', $response->getHeaderLine('X-Grotto-Image-Sanitized'));
        self::assertStringNotContainsString('GrottoTestMetadata', (string) $sanitizedBinary);
        self::assertCount(3, $this->history);
        self::assertStringEndsWith('/api/v1/tasks/task-1', (string) $this->history[1]['request']->getUri());
        self::assertStringEndsWith('/api/v1/tasks/task-1', (string) $this->history[2]['request']->getUri());

        foreach ($this->history as $entry) {
            self::assertSame('Bearer test-platform-key', $entry['request']->getHeaderLine('Authorization'));
        }
    }

    public function testModelListIsNormalized(): void
    {
        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [
                    ['id' => 'qwen-plus'],
                    ['id' => 'qwen-max', 'owned_by' => 'bailian'],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $app = $this->application($client);

        $response = $app->handle(new ServerRequest(
            'GET',
            'https://grotto.test/bailian/v1/models',
            ['Authorization' => 'Bearer test-platform-key']
        ));
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('list', $payload['object']);
        self::assertCount(2, $payload['data']);
        self::assertSame('model', $payload['data'][0]['object']);
        self::assertSame('qwen-max', $payload['data'][1]['id']);
        self::assertSame('bailian', $payload['data'][1]['owned_by']);
    }

    public function testUpstreamAuthenticationErrorsAreSanitized(): void
    {
        $client = $this->mockClient([
            new Response(401, ['Content-Type' => 'application/json'], json_encode([
                'code' => 'InvalidApiKey',
                'message' => 'Bad key sk-not-a-real-key from https://internal.example 10.1.2.3',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $app = $this->application($client);

        $response = $app->handle(new ServerRequest(
            'POST',
            'https://grotto.test/bailian/v1/chat/completions',
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer rejected-platform-key',
            ],
            json_encode([
                'model' => 'qwen-plus',
                'messages' => [['role' => 'user', 'content' => 'hello']],
            ], JSON_THROW_ON_ERROR)
        ));
        $body = $this->body($response);
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(502, $response->getStatusCode());
        self::assertSame('bailian_invalid_api_key', $payload['error']['code']);
        self::assertStringNotContainsString('sk-not-a-real-key', $body);
        self::assertStringNotContainsString('internal.example', $body);
    }

    public function testRequestKeyIsForwardedInsteadOfEnvironmentValue(): void
    {
        $client = $this->mockClient([
            new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
        ]);
        $app = $this->application($client, ['BAILIAN_API_KEY' => 'environment-key-must-not-be-used']);

        $response = $app->handle(new ServerRequest(
            'GET',
            'https://grotto.test/bailian/v1/models',
            ['Authorization' => 'Bearer request-key']
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Bearer request-key', $this->history[0]['request']->getHeaderLine('Authorization'));
    }

    public function testProxyRejectsMissingPlatformKeyBeforeCallingUpstream(): void
    {
        $client = $this->mockClient([]);
        $app = $this->application($client);

        $response = $app->handle(new ServerRequest(
            'POST',
            'https://grotto.test/bailian/v1/chat/completions',
            ['Content-Type' => 'application/json'],
            json_encode([
                'model' => 'qwen-plus',
                'messages' => [['role' => 'user', 'content' => 'hello']],
            ], JSON_THROW_ON_ERROR)
        ));
        $payload = json_decode($this->body($response), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('missing_api_key', $payload['error']['code']);
        self::assertCount(0, $this->history);
    }

    /**
     * @param array<string, string> $overrides
     */
    private function application(ClientInterface $client, array $overrides = []): \Slim\App
    {
        $environment = array_merge($this->defaultEnvironment(), [
            'GROTTO_PLATFORMS' => 'bailian',
            'BAILIAN_BASE_URL' => 'https://upstream.test',
            'GROTTO_ASYNC_POLL_INTERVAL_MS' => '1',
            'GROTTO_ASYNC_TIMEOUT' => '3',
        ], $overrides);

        return ApplicationFactory::create($environment, [
            ClientInterface::class => static fn (): ClientInterface => $client,
        ]);
    }

    /**
     * @param list<Response> $responses
     */
    private function mockClient(array $responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $history = &$this->history;
        $stack->push(Middleware::history($history));

        return new Client([
            'handler' => $stack,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
    }

    private function body(MessageInterface $response): string
    {
        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream->getContents();
    }

    private function pngWithTextMetadata(): string
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        };

        return "\x89PNG\r\n\x1A\n"
            . $chunk('IHDR', str_repeat("\x00", 13))
            . $chunk('tEXt', "Software\x00GrottoTestMetadata")
            . $chunk('IDAT', 'pixel-data')
            . $chunk('IEND', '');
    }
}
