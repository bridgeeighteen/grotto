<?php

declare(strict_types=1);

namespace Grotto\Platform\Volcengine;

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

final class VolcengineAdapter extends AbstractAdapter
{
    private readonly VolcengineVideoTask $videoTask;

    public function __construct(
        Config $config,
        ClientInterface $http,
        UpstreamErrorMapper $errors,
        LoggerInterface $logger,
    ) {
        parent::__construct($config, $http, $errors, $logger);
        $this->videoTask = new VolcengineVideoTask($http, $config, $errors, $logger, $this->baseUrl());
    }

    public function id(): string
    {
        return 'volcengine';
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
                $this->headers($ctx)
            ),
            PlatformCapability::VIDEOS => UpstreamRequest::json(
                'POST',
                $this->uri('contents/generations/tasks'),
                $this->videoBody($openAiBody),
                $this->headers($ctx)
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
                sprintf('Volcengine Ark does not support %s.', $capability),
                'volcengine_unsupported_capability'
            ),
        };
    }

    public function toOpenAi(string $capability, ResponseInterface $upstreamResponse, RequestContext $ctx): OpenAiResponse
    {
        if ($capability === PlatformCapability::VIDEOS) {
            return $this->videoTask->toOpenAi($this->jsonBody($upstreamResponse), $ctx);
        }

        return $this->passthrough($upstreamResponse);
    }

    public function needsAsyncPolling(string $capability, ResponseInterface $submission): bool
    {
        if ($capability !== PlatformCapability::VIDEOS || $submission->getStatusCode() >= 400) {
            return false;
        }

        try {
            $payload = $this->jsonBody($submission);
        } catch (ApiException) {
            return false;
        }

        $taskId = $payload['id'] ?? $payload['task_id'] ?? null;

        if (!is_string($taskId) || $taskId === '') {
            return false;
        }

        return !isset($payload['content']['video_url'])
            && !isset($payload['video_url'])
            && !isset($payload['data']);
    }

    public function resolveAsync(string $capability, ResponseInterface $submission, RequestContext $ctx): OpenAiResponse
    {
        try {
            $payload = $this->jsonBody($submission);
        } catch (ApiException) {
            $payload = [];
        }

        $taskId = $payload['id'] ?? $payload['task_id'] ?? null;

        if (!is_string($taskId) || $taskId === '') {
            throw ApiException::upstream(
                'Volcengine Ark accepted the job but did not return a task ID.',
                'volcengine_missing_task_id'
            );
        }

        $task = $this->videoTask->wait($taskId, $ctx, $this->headers($ctx));
        $task['id'] ??= $taskId;

        return $this->videoTask->toOpenAi($task, $ctx);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function videoBody(array $body): array
    {
        $prompt = isset($body['prompt']) && is_string($body['prompt']) ? trim($body['prompt']) : '';
        $image = $body['image_url'] ?? $body['image'] ?? null;

        if (is_array($image)) {
            $image = $image['url'] ?? $image['image_url'] ?? null;
        }

        $content = [];

        if ($prompt !== '') {
            $content[] = ['type' => 'text', 'text' => $prompt];
        }

        if (is_string($image) && $image !== '') {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $image],
            ];
        }

        $payload = [
            'model' => $body['model'],
            'content' => $content,
        ];

        foreach (['ratio', 'duration', 'seed', 'watermark', 'resolution', 'camera_fixed'] as $field) {
            if (array_key_exists($field, $body) && $body[$field] !== null && $body[$field] !== '') {
                $payload[$field] = $body[$field];
            }
        }

        return $payload;
    }
}
