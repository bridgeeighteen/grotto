<?php

declare(strict_types=1);

namespace Grotto\Tests\Core;

use Grotto\Core\Exception\ApiException;
use Grotto\Core\RequestNormalizer;
use Grotto\Platform\PlatformCapability;
use Grotto\Tests\TestCase;
use GuzzleHttp\Psr7\ServerRequest;

final class RequestNormalizerTest extends TestCase
{
    private RequestNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new RequestNormalizer();
    }

    public function testNormalizesChatRequest(): void
    {
        $request = $this->jsonRequest([
            'model' => 'qwen-plus',
            'messages' => [['role' => 'user', 'content' => 'hello']],
            'stream' => true,
        ]);

        $body = $this->normalizer->normalize($request, PlatformCapability::CHAT);

        self::assertSame('qwen-plus', $body['model']);
        self::assertTrue($body['stream']);
    }

    public function testRejectsInvalidJson(): void
    {
        $request = new ServerRequest('POST', '/', ['Content-Type' => 'application/json'], '{bad');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('not valid JSON');

        $this->normalizer->normalize($request, PlatformCapability::CHAT);
    }

    public function testRequiresModel(): void
    {
        $request = $this->jsonRequest([
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('"model"');

        $this->normalizer->normalize($request, PlatformCapability::CHAT);
    }

    public function testRequiresMessages(): void
    {
        $request = $this->jsonRequest([
            'model' => 'test',
            'messages' => [],
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('"messages"');

        $this->normalizer->normalize($request, PlatformCapability::CHAT);
    }

    public function testRejectsStreamingForImages(): void
    {
        $request = $this->jsonRequest([
            'model' => 'wanx',
            'prompt' => 'a grotto',
            'stream' => true,
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Streaming is only available');

        $this->normalizer->normalize($request, PlatformCapability::IMAGES);
    }

    public function testModelListDoesNotRequireBody(): void
    {
        $request = new ServerRequest('GET', '/models');

        self::assertSame([], $this->normalizer->normalize($request, PlatformCapability::MODELS));
    }

    public function testSpeechToTextRequiresMultipart(): void
    {
        $request = new ServerRequest('POST', '/audio/transcriptions', [
            'Content-Type' => 'application/json',
        ], '{}');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('multipart/form-data');

        $this->normalizer->normalize($request, PlatformCapability::STT);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(array $body): ServerRequest
    {
        return new ServerRequest(
            'POST',
            '/v1/chat/completions',
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR)
        );
    }
}
