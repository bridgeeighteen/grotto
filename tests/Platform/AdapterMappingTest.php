<?php

declare(strict_types=1);

namespace Grotto\Tests\Platform;

use Grotto\Config\Config;
use Grotto\Core\UpstreamErrorMapper;
use Grotto\Platform\Bailian\BailianAdapter;
use Grotto\Platform\ModelScope\ModelScopeAdapter;
use Grotto\Platform\PlatformCapability;
use Grotto\Platform\RequestContext;
use Grotto\Platform\Volcengine\VolcengineAdapter;
use Grotto\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

final class AdapterMappingTest extends TestCase
{
    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    public function testBailianChatMapsToCompatibleEndpointAndKeepsBody(): void
    {
        $adapter = $this->bailianAdapter();

        $ctx = $this->context('bailian', PlatformCapability::CHAT, stream: true);
        $request = $adapter->toUpstream(PlatformCapability::CHAT, [
            'model' => 'qwen-plus',
            'messages' => [['role' => 'user', 'content' => 'hello']],
            'stream' => true,
        ], $ctx);

        self::assertSame('POST', $request->method);
        self::assertSame('https://upstream.test/compatible-mode/v1/chat/completions', $request->uri);
        self::assertSame('Bearer request-platform-key', $request->headers['Authorization']);
        self::assertSame('text/event-stream', $request->headers['Accept']);
        self::assertTrue($request->stream);
        self::assertSame('qwen-plus', json_decode((string) $request->body, true)['model']);
    }

    public function testBailianImageUsesNativeAsyncEndpoint(): void
    {
        $adapter = $this->bailianAdapter();
        $ctx = $this->context('bailian', PlatformCapability::IMAGES);
        $request = $adapter->toUpstream(PlatformCapability::IMAGES, [
            'model' => 'wanx2.1-t2i-turbo',
            'prompt' => 'a grotto',
            'size' => '1024x1024',
            'n' => 2,
            'negative_prompt' => 'text',
        ], $ctx);
        $body = json_decode((string) $request->body, true);

        self::assertSame(
            'https://upstream.test/api/v1/services/aigc/text2image/image-synthesis',
            $request->uri
        );
        self::assertSame('enable', $request->headers['X-DashScope-Async']);
        self::assertSame('a grotto', $body['input']['prompt']);
        self::assertSame('1024*1024', $body['parameters']['size']);
        self::assertSame(2, $body['parameters']['n']);
    }

    public function testBailianDetectsAsyncImageTaskAndNormalizesResult(): void
    {
        $adapter = $this->bailianAdapter();
        $ctx = $this->context('bailian', PlatformCapability::IMAGES);
        $submission = new Response(200, [], json_encode([
            'output' => ['task_id' => 'task-1', 'task_status' => 'PENDING'],
            'request_id' => 'req',
        ], JSON_THROW_ON_ERROR));

        self::assertTrue($adapter->needsAsyncPolling(PlatformCapability::IMAGES, $submission));

        $final = new Response(200, [], json_encode([
            'output' => [
                'task_id' => 'task-1',
                'task_status' => 'SUCCEEDED',
                'results' => [['url' => 'https://cdn.example/image.png']],
            ],
            'created' => 123,
        ], JSON_THROW_ON_ERROR));

        $normalized = $adapter->toOpenAi(PlatformCapability::IMAGES, $final, $ctx);
        $payload = json_decode((string) $normalized->body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(123, $payload['created']);
        self::assertSame('https://cdn.example/image.png', $payload['data'][0]['url']);
    }

    public function testModelScopeImageCarriesAsyncHeader(): void
    {
        $config = $this->config([
            'MODELSCOPE_BASE_URL' => 'https://modelscope.test/v1',
        ]);
        $adapter = new ModelScopeAdapter(
            $config,
            $this->client([]),
            new UpstreamErrorMapper(new NullLogger()),
            new NullLogger()
        );
        $ctx = $this->context('modelscope', PlatformCapability::IMAGES);
        $request = $adapter->toUpstream(PlatformCapability::IMAGES, [
            'model' => 'Qwen/Qwen-Image-2.1',
            'prompt' => 'a grotto',
        ], $ctx);

        self::assertSame('https://modelscope.test/v1/images/generations', $request->uri);
        self::assertSame('true', $request->headers['X-ModelScope-Async-Mode']);
        self::assertSame('Bearer request-platform-key', $request->headers['Authorization']);
    }

    public function testVolcengineVideoSubmissionAndPolling(): void
    {
        $config = $this->config([
            'VOLCENGINE_BASE_URL' => 'https://ark.test/api/v3',
        ]);
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 'running',
                'id' => 'cgt-123',
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'status' => 'succeeded',
                'id' => 'cgt-123',
                'content' => ['video_url' => 'https://cdn.example/video.mp4'],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $adapter = new VolcengineAdapter(
            $config,
            $this->clientWithMock($mock),
            new UpstreamErrorMapper(new NullLogger()),
            new NullLogger()
        );
        $ctx = $this->context('volcengine', PlatformCapability::VIDEOS, model: 'seedance');
        $submission = new Response(200, [], json_encode(['id' => 'cgt-123'], JSON_THROW_ON_ERROR));

        self::assertTrue($adapter->needsAsyncPolling(PlatformCapability::VIDEOS, $submission));

        $response = $adapter->resolveAsync(PlatformCapability::VIDEOS, $submission, $ctx);
        $payload = json_decode((string) $response->body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('cgt-123', $payload['id']);
        self::assertSame('completed', $payload['status']);
        self::assertSame('https://cdn.example/video.mp4', $payload['data'][0]['url']);
        self::assertCount(2, $this->history);
        self::assertSame(
            'https://ark.test/api/v3/contents/generations/tasks/cgt-123',
            (string) $this->history[0]['request']->getUri()
        );
        self::assertSame(
            'Bearer request-platform-key',
            $this->history[0]['request']->getHeaderLine('Authorization')
        );
    }

    public function testVolcengineVideoBodyMapsContentAndParameters(): void
    {
        $config = $this->config([
            'VOLCENGINE_BASE_URL' => 'https://ark.test/api/v3',
        ]);
        $adapter = new VolcengineAdapter(
            $config,
            $this->client([]),
            new UpstreamErrorMapper(new NullLogger()),
            new NullLogger()
        );
        $request = $adapter->toUpstream(PlatformCapability::VIDEOS, [
            'model' => 'seedance',
            'prompt' => 'slow dolly',
            'image_url' => 'https://example.test/frame.png',
            'ratio' => '16:9',
            'duration' => 5,
        ], $this->context('volcengine', PlatformCapability::VIDEOS));
        $body = json_decode((string) $request->body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('seedance', $body['model']);
        self::assertSame('slow dolly', $body['content'][0]['text']);
        self::assertSame('https://example.test/frame.png', $body['content'][1]['image_url']['url']);
        self::assertSame('16:9', $body['ratio']);
        self::assertSame(5, $body['duration']);
    }

    private function bailianAdapter(): BailianAdapter
    {
        $config = $this->config([
            'BAILIAN_BASE_URL' => 'https://upstream.test',
        ]);

        return new BailianAdapter(
            $config,
            $this->client([]),
            new UpstreamErrorMapper(new NullLogger()),
            new NullLogger()
        );
    }

    /**
     * @param list<Response> $responses
     */
    private function client(array $responses): Client
    {
        return $this->clientWithMock(new MockHandler($responses));
    }

    private function clientWithMock(MockHandler $mock): Client
    {
        $stack = HandlerStack::create($mock);
        $history = &$this->history;
        $stack->push(Middleware::history($history));

        return new Client([
            'handler' => $stack,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
    }

    private function context(
        string $platform,
        string $capability,
        bool $stream = false,
        ?string $model = 'test-model',
        string $apiKey = 'request-platform-key',
    ): RequestContext {
        return new RequestContext(
            platform: $platform,
            capability: $capability,
            path: PlatformCapability::openAiPath($capability) ?? '',
            requestId: 'req_test',
            clientId: 'client',
            stream: $stream,
            model: $model,
            apiKey: $apiKey,
        );
    }
}
