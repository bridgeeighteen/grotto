<?php

declare(strict_types=1);

namespace Grotto\Tests\Core;

use Grotto\Core\UpstreamErrorMapper;
use Grotto\Platform\RequestContext;
use Grotto\Tests\TestCase;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;

final class UpstreamErrorMapperTest extends TestCase
{
    public function testMapsUpstreamAuthenticationFailureWithoutLeakingDetails(): void
    {
        $mapper = new UpstreamErrorMapper(new NullLogger());
        $response = new Response(401, ['Content-Type' => 'application/json'], json_encode([
            'request_id' => '123e4567-e89b-12d3-a456-426614174000',
            'code' => 'InvalidApiKey',
            'message' => 'Invalid key sk-super-secret for https://internal.example.test at 10.1.2.3',
        ], JSON_THROW_ON_ERROR));

        $exception = $mapper->fromResponse($response, 'bailian', $this->context());

        self::assertSame(502, $exception->status);
        self::assertSame('bailian_invalid_api_key', $exception->errorCode);
        self::assertStringContainsString('Bailian rejected', $exception->getMessage());
        self::assertStringNotContainsString('sk-super-secret', $exception->getMessage());
        self::assertStringNotContainsString('internal.example.test', $exception->getMessage());
        self::assertStringNotContainsString('123e4567', $exception->getMessage());
    }

    public function testPreservesRateLimitStatus(): void
    {
        $mapper = new UpstreamErrorMapper(new NullLogger());
        $response = new Response(429, [], json_encode([
            'code' => 'Throttling',
            'message' => 'Rate limit exceeded',
        ], JSON_THROW_ON_ERROR));

        $exception = $mapper->fromResponse($response, 'modelscope', $this->context());

        self::assertSame(429, $exception->status);
        self::assertSame('rate_limit_error', $exception->errorType);
        self::assertSame('modelscope_rate_limited', $exception->errorCode);
    }

    public function testMapsQuotaFailuresToOpenAiInsufficientQuota(): void
    {
        $mapper = new UpstreamErrorMapper(new NullLogger());
        $response = new Response(403, [], json_encode([
            'code' => 'Arrearage',
            'message' => 'Account arrears; quota exhausted.',
        ], JSON_THROW_ON_ERROR));

        $exception = $mapper->fromResponse($response, 'bailian', $this->context());

        self::assertSame(429, $exception->status);
        self::assertSame('insufficient_quota', $exception->errorType);
        self::assertSame('bailian_quota_exceeded', $exception->errorCode);
    }

    public function testIncludesSanitizedValidationDetail(): void
    {
        $mapper = new UpstreamErrorMapper(new NullLogger());
        $response = new Response(400, [], json_encode([
            'error' => ['message' => 'Parameter size is invalid; see https://internal.example/help'],
        ], JSON_THROW_ON_ERROR));

        $exception = $mapper->fromResponse($response, 'volcengine', $this->context());

        self::assertSame(400, $exception->status);
        self::assertStringContainsString('Parameter size is invalid', $exception->getMessage());
        self::assertStringNotContainsString('internal.example', $exception->getMessage());
        self::assertStringContainsString('[redacted-url]', $exception->getMessage());
    }

    public function testRedactsPotentialSecrets(): void
    {
        $mapper = new UpstreamErrorMapper(new NullLogger());
        $redacted = $mapper->redact(
            'Bearer abc123 key sk-abcdefghijklmnop IP 192.168.1.1 mail x@example.com id 123e4567-e89b-12d3-a456-426614174000'
        );

        self::assertStringNotContainsString('abc123', $redacted);
        self::assertStringNotContainsString('sk-abcdefghijklmnop', $redacted);
        self::assertStringNotContainsString('192.168.1.1', $redacted);
        self::assertStringNotContainsString('x@example.com', $redacted);
        self::assertStringNotContainsString('123e4567', $redacted);
    }

    private function context(): RequestContext
    {
        return new RequestContext(
            platform: 'bailian',
            capability: 'chat',
            path: 'chat/completions',
            requestId: 'req_test',
            clientId: 'client',
            stream: false,
        );
    }
}
