<?php

declare(strict_types=1);

namespace Grotto\Core;

use Grotto\Core\Exception\ApiException;
use Grotto\Platform\RequestContext;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Converts platform-specific errors into a stable, sanitized OpenAI error
 * envelope. Raw upstream details are logged, never returned verbatim.
 */
final class UpstreamErrorMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fromResponse(
        ResponseInterface $response,
        string $platform,
        RequestContext $ctx,
    ): ApiException {
        $status = $response->getStatusCode();
        $body = $this->readBody($response);
        $decoded = json_decode($body, true);
        $decoded = is_array($decoded) ? $decoded : [];

        $rawCode = $this->extractCode($decoded);
        $rawMessage = $this->extractMessage($decoded);
        $code = $this->normalizeCode($rawCode, $platform, $status);

        $this->logger->warning('Upstream request failed', [
            'platform' => $platform,
            'request_id' => $ctx->requestId,
            'capability' => $ctx->capability,
            'status' => $status,
            'code' => $rawCode,
            'detail' => $this->redact($rawMessage !== '' ? $rawMessage : $body),
        ]);

        [$mappedStatus, $type, $message] = $this->classify($status, $rawCode, $rawMessage, $platform, $code);

        return new ApiException($mappedStatus, $type, $code, $message);
    }

    public function fromThrowable(Throwable $exception, string $platform, RequestContext $ctx): ApiException
    {
        $this->logger->error('Upstream request could not complete', [
            'platform' => $platform,
            'request_id' => $ctx->requestId,
            'capability' => $ctx->capability,
            'exception' => $exception::class,
            'detail' => $this->redact($exception->getMessage()),
        ]);

        if ($exception instanceof ConnectException) {
            return ApiException::upstream(
                sprintf('%s could not be reached from Grotto.', $this->platformLabel($platform)),
                $platform . '_upstream_unreachable',
                503
            );
        }

        if ($exception instanceof RequestException && $exception->getResponse() !== null) {
            return $this->fromResponse($exception->getResponse(), $platform, $ctx);
        }

        return ApiException::upstream(
            sprintf('%s did not complete the request in time.', $this->platformLabel($platform)),
            $platform . '_upstream_failed',
            502
        );
    }

    /**
     * @param array<string, mixed> $raw
     */
    public function taskFailed(string $platform, string $reason, RequestContext $ctx, array $raw = []): ApiException
    {
        $this->logger->warning('Upstream asynchronous task failed', [
            'platform' => $platform,
            'request_id' => $ctx->requestId,
            'capability' => $ctx->capability,
            'detail' => $this->redact($reason),
        ]);

        unset($raw);

        return ApiException::upstream(
            sprintf('%s could not complete the %s task. Check the gateway logs for the upstream reason.',
                $this->platformLabel($platform),
                $ctx->capability
            ),
            $platform . '_task_failed'
        );
    }

    public function timeout(string $platform, RequestContext $ctx): ApiException
    {
        $this->logger->warning('Upstream asynchronous task timed out', [
            'platform' => $platform,
            'request_id' => $ctx->requestId,
            'capability' => $ctx->capability,
        ]);

        return ApiException::upstream(
            sprintf('%s did not finish the task before the gateway timeout.', $this->platformLabel($platform)),
            $platform . '_task_timeout',
            504
        );
    }

    public function redact(string $value): string
    {
        $value = preg_replace('~https?://[^\s"\'<>]+~i', '[redacted-url]', $value) ?? $value;
        $value = preg_replace('/\b(?:sk|key|tok|ak)-[A-Za-z0-9_\-]{8,}\b/i', '[redacted-key]', $value) ?? $value;
        $value = preg_replace('/\bBearer\s+[^\s"\'<>]+/i', 'Bearer [redacted]', $value) ?? $value;
        $value = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[redacted-ip]', $value) ?? $value;
        $value = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i', '[redacted-id]', $value) ?? $value;
        $value = preg_replace('/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/', '[redacted-email]', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? $value;

        return mb_strlen($value) > 320 ? mb_substr($value, 0, 317) . '…' : $value;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractCode(array $decoded): string
    {
        $candidates = [
            $decoded['error']['code'] ?? null,
            $decoded['code'] ?? null,
            $decoded['error']['type'] ?? null,
            $decoded['status'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractMessage(array $decoded): string
    {
        $candidates = [
            $decoded['error']['message'] ?? null,
            $decoded['message'] ?? null,
            $decoded['error_msg'] ?? null,
            $decoded['errors'][0]['message'] ?? null,
            $decoded['error'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function normalizeCode(string $rawCode, string $platform, int $status): string
    {
        $normalized = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $rawCode));
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            $normalized = match (true) {
                $status === 401 || $status === 403 => 'auth_failed',
                $status === 404 => 'not_found',
                $status === 429 => 'rate_limited',
                $status >= 500 => 'upstream_error',
                default => 'upstream_request_failed',
            };
        }

        if (str_contains($normalized, 'apikey') || str_contains($normalized, 'api_key') || str_contains($normalized, 'unauthorized')) {
            $normalized = 'invalid_api_key';
        } elseif (str_contains($normalized, 'quota') || str_contains($normalized, 'arrear')) {
            $normalized = 'quota_exceeded';
        } elseif (str_contains($normalized, 'rate') || $status === 429) {
            $normalized = 'rate_limited';
        } elseif (str_contains($normalized, 'model') && str_contains($normalized, 'not')) {
            $normalized = 'model_not_found';
        }

        return str_starts_with($normalized, $platform . '_')
            ? $normalized
            : $platform . '_' . $normalized;
    }

    /**
     * @return array{int, string, string}
     */
    private function classify(int $status, string $rawCode, string $rawMessage, string $platform, string $code): array
    {
        $label = $this->platformLabel($platform);
        $lowerCode = strtolower($rawCode);
        $lowerMessage = strtolower($rawMessage);

        if (str_contains($lowerCode, 'quota') || str_contains($lowerCode, 'arrear') || str_contains($lowerMessage, 'quota')) {
            return [429, 'insufficient_quota', sprintf('The %s account cannot serve this request. Check quota and billing.', $label)];
        }

        if ($status === 401 || $status === 403 || str_contains($lowerCode, 'invalidapikey') || str_contains($lowerCode, 'unauthorized')) {
            return [502, 'upstream_error', sprintf('%s rejected the API key supplied for this request.', $label)];
        }

        if ($status === 429) {
            return [429, 'rate_limit_error', sprintf('%s rate limited the gateway. Retry shortly.', $label)];
        }

        if (str_contains($code, 'model_not_found')) {
            return [404, 'invalid_request_error', sprintf('The requested model is not available on %s.', $label)];
        }

        if ($status === 400 || $status === 422) {
            $safe = $this->safeDetail($rawMessage);
            return [400, 'invalid_request_error', $safe !== ''
                ? sprintf('%s rejected the request: %s', $label, $safe)
                : sprintf('%s rejected the request shape.', $label)];
        }

        if ($status === 404) {
            return [404, 'invalid_request_error', sprintf('%s did not recognize the requested resource.', $label)];
        }

        if ($status >= 500) {
            return [502, 'upstream_error', sprintf('%s returned a server error.', $label)];
        }

        return [502, 'upstream_error', sprintf('%s returned an unexpected response.', $label)];
    }

    private function safeDetail(string $message): string
    {
        if ($message === '') {
            return '';
        }

        return $this->redact($message);
    }

    private function readBody(ResponseInterface $response): string
    {
        $stream = $response->getBody();
        $contents = $stream->getContents();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $contents;
    }

    private function platformLabel(string $platform): string
    {
        return match ($platform) {
            'bailian' => 'Bailian',
            'modelscope' => 'ModelScope',
            'volcengine' => 'Volcengine Ark',
            default => ucfirst($platform),
        };
    }
}
