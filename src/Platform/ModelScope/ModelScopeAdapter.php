<?php

declare(strict_types=1);

namespace Grotto\Platform\ModelScope;

use Grotto\Config\Config;
use Grotto\Core\Exception\ApiException;
use Grotto\Core\UpstreamErrorMapper;
use Grotto\Platform\AbstractAdapter;
use Grotto\Platform\OpenAiResponse;
use Grotto\Platform\PlatformCapability;
use Grotto\Platform\RequestContext;
use Grotto\Platform\UpstreamRequest;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class ModelScopeAdapter extends AbstractAdapter
{
    private readonly ModelScopeImageTask $imageTask;

    private readonly ModelScopeVideoTask $videoTask;

    public function __construct(
        Config $config,
        ClientInterface $http,
        UpstreamErrorMapper $errors,
        LoggerInterface $logger,
    ) {
        parent::__construct($config, $http, $errors, $logger);
        $this->imageTask = new ModelScopeImageTask($http, $config, $errors, $logger, $this->baseUrl());
        $this->videoTask = new ModelScopeVideoTask($http, $config, $errors, $logger, $this->baseUrl());
    }

    public function id(): string
    {
        return 'modelscope';
    }

    public function toUpstream(string $capability, array $openAiBody, RequestContext $ctx): UpstreamRequest
    {
        return match ($capability) {
            PlatformCapability::CHAT => UpstreamRequest::json(
                'POST',
                $this->uri('chat/completions'),
                $openAiBody,
                $this->headers($ctx),
                $ctx->stream
            ),
            PlatformCapability::MODELS => UpstreamRequest::raw(
                'GET',
                $this->uri('models'),
                null,
                $this->headers($ctx)
            ),
            PlatformCapability::IMAGES => UpstreamRequest::json(
                'POST',
                $this->uri('images/generations'),
                $openAiBody,
                $this->headers($ctx, ['X-ModelScope-Async-Mode' => 'true'])
            ),
            PlatformCapability::VIDEOS => UpstreamRequest::json(
                'POST',
                $this->uri('videos/generations'),
                $openAiBody,
                $this->headers($ctx, ['X-ModelScope-Async-Mode' => 'true'])
            ),
            PlatformCapability::TTS => UpstreamRequest::json(
                'POST',
                $this->uri('audio/speech'),
                $openAiBody,
                $this->headers($ctx, ['Accept' => 'audio/*'])
            ),
            PlatformCapability::STT => UpstreamRequest::raw(
                'POST',
                $this->uri('audio/transcriptions'),
                $ctx->rawBody,
                $this->headers($ctx, ['Content-Type' => $ctx->contentType])
            ),
            default => throw ApiException::unsupported(
                sprintf('ModelScope does not support %s on this instance.', $capability),
                'modelscope_unsupported_capability'
            ),
        };
    }

    public function toOpenAi(string $capability, ResponseInterface $upstreamResponse, RequestContext $ctx): OpenAiResponse
    {
        if ($capability === PlatformCapability::IMAGES) {
            return OpenAiResponse::json(
                $this->normalizeImagePayload($this->jsonBody($upstreamResponse)),
                $upstreamResponse->getStatusCode()
            );
        }

        if ($capability === PlatformCapability::VIDEOS) {
            return OpenAiResponse::json(
                $this->normalizeVideoPayload($this->jsonBody($upstreamResponse), $ctx),
                $upstreamResponse->getStatusCode()
            );
        }

        return $this->passthrough($upstreamResponse);
    }

    public function needsAsyncPolling(string $capability, ResponseInterface $submission): bool
    {
        if (!in_array($capability, [PlatformCapability::IMAGES, PlatformCapability::VIDEOS], true)) {
            return false;
        }

        if ($submission->getStatusCode() >= 400) {
            return false;
        }

        try {
            $payload = $this->jsonBody($submission);
        } catch (ApiException) {
            return false;
        }

        $taskId = $payload['task_id'] ?? $payload['taskId'] ?? $this->taskIdFromHeaders($submission);

        if (!is_string($taskId) || $taskId === '') {
            return false;
        }

        return !isset($payload['data'])
            && !isset($payload['output_images'])
            && !isset($payload['output_videos'])
            && !isset($payload['video_url']);
    }

    public function resolveAsync(string $capability, ResponseInterface $submission, RequestContext $ctx): OpenAiResponse
    {
        try {
            $payload = $this->jsonBody($submission);
        } catch (ApiException) {
            $payload = [];
        }

        $taskId = $payload['task_id'] ?? $payload['taskId'] ?? $this->taskIdFromHeaders($submission);

        if (!is_string($taskId) || $taskId === '') {
            throw ApiException::upstream(
                'ModelScope accepted the job but did not return a task ID.',
                'modelscope_missing_task_id'
            );
        }

        $headers = $this->headers($ctx);

        if ($capability === PlatformCapability::IMAGES) {
            return $this->imageTask->toOpenAi($this->imageTask->wait($taskId, $ctx, $headers), $ctx);
        }

        if ($capability === PlatformCapability::VIDEOS) {
            $task = $this->videoTask->wait($taskId, $ctx, $headers);
            $task['task_id'] ??= $taskId;

            return $this->videoTask->toOpenAi($task, $ctx);
        }

        throw ApiException::unsupported(
            sprintf('ModelScope does not support asynchronous %s.', $capability),
            'modelscope_async_unsupported'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeImagePayload(array $payload): array
    {
        $images = $payload['output_images'] ?? $payload['output']['images'] ?? $payload['data'] ?? [];
        $data = [];

        if (is_string($images)) {
            $images = [$images];
        }

        if (is_array($images)) {
            foreach ($images as $image) {
                if (is_string($image)) {
                    $data[] = ['url' => $image];
                    continue;
                }

                if (!is_array($image)) {
                    continue;
                }

                $item = [];

                if (isset($image['url']) && is_string($image['url'])) {
                    $item['url'] = $image['url'];
                }

                if (isset($image['b64_json']) && is_string($image['b64_json'])) {
                    $item['b64_json'] = $image['b64_json'];
                }

                if ($item !== []) {
                    $data[] = $item;
                }
            }
        }

        if ($data === []) {
            throw ApiException::upstream(
                'ModelScope did not return a completed image.',
                'modelscope_missing_image'
            );
        }

        return [
            'created' => isset($payload['created']) && is_numeric($payload['created'])
                ? (int) $payload['created']
                : time(),
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeVideoPayload(array $payload, RequestContext $ctx): array
    {
        $url = $payload['output']['video_url'] ?? $payload['video_url'] ?? null;
        $videos = $payload['output_videos'] ?? $payload['output']['videos'] ?? null;

        if (is_string($videos)) {
            $videos = [$videos];
        }

        if ((!is_string($url) || $url === '') && is_array($videos)) {
            foreach ($videos as $video) {
                if (is_string($video)) {
                    $url = $video;
                    break;
                }

                if (is_array($video) && isset($video['url']) && is_string($video['url'])) {
                    $url = $video['url'];
                    break;
                }
            }
        }

        if (!is_string($url) || $url === '') {
            throw ApiException::upstream(
                'ModelScope did not return a completed video.',
                'modelscope_missing_video'
            );
        }

        $taskId = $payload['task_id'] ?? null;

        return [
            'id' => is_string($taskId) ? $taskId : null,
            'object' => 'video',
            'created' => isset($payload['created']) && is_numeric($payload['created'])
                ? (int) $payload['created']
                : time(),
            'model' => $ctx->model,
            'status' => 'completed',
            'data' => [['url' => $url]],
        ];
    }

    private function taskIdFromHeaders(ResponseInterface $response): ?string
    {
        foreach (['X-ModelScope-Task-Id', 'X-Task-Id'] as $header) {
            $value = $response->getHeaderLine($header);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
