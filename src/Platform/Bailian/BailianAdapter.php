<?php

declare(strict_types=1);

namespace Grotto\Platform\Bailian;

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

final class BailianAdapter extends AbstractAdapter
{
    private readonly BailianEndpointResolver $resolver;

    private readonly BailianAsyncPoller $poller;

    public function __construct(
        Config $config,
        ClientInterface $http,
        UpstreamErrorMapper $errors,
        LoggerInterface $logger,
    ) {
        parent::__construct($config, $http, $errors, $logger);
        $this->resolver = new BailianEndpointResolver($this->platform);
        $this->poller = new BailianAsyncPoller($http, $config, $errors, $logger, $this->resolver);
    }

    public function id(): string
    {
        return 'bailian';
    }

    public function toUpstream(string $capability, array $openAiBody, RequestContext $ctx): UpstreamRequest
    {
        return match ($capability) {
            PlatformCapability::CHAT => UpstreamRequest::json(
                'POST',
                $this->resolver->compatibleUrl($ctx, 'chat/completions'),
                $openAiBody,
                $this->headers($ctx),
                $ctx->stream
            ),
            PlatformCapability::MODELS => UpstreamRequest::raw(
                'GET',
                $this->resolver->compatibleUrl($ctx, 'models'),
                null,
                $this->headers($ctx)
            ),
            PlatformCapability::IMAGES => UpstreamRequest::json(
                'POST',
                $this->resolver->nativeUrl($ctx, 'services/aigc/text2image/image-synthesis'),
                $this->imageBody($openAiBody),
                $this->headers($ctx, ['X-DashScope-Async' => 'enable'])
            ),
            PlatformCapability::VIDEOS => UpstreamRequest::json(
                'POST',
                $this->resolver->nativeUrl($ctx, 'services/aigc/video-generation/video-synthesis'),
                $this->videoBody($openAiBody),
                $this->headers($ctx, ['X-DashScope-Async' => 'enable'])
            ),
            PlatformCapability::TTS => UpstreamRequest::json(
                'POST',
                $this->resolver->compatibleUrl($ctx, 'audio/speech'),
                $openAiBody,
                $this->headers($ctx, ['Accept' => 'audio/*'])
            ),
            PlatformCapability::STT => UpstreamRequest::raw(
                'POST',
                $this->resolver->compatibleUrl($ctx, 'audio/transcriptions'),
                $ctx->rawBody,
                $this->headers($ctx, ['Content-Type' => $ctx->contentType])
            ),
            default => throw ApiException::unsupported(
                sprintf('Bailian does not support %s.', $capability),
                'bailian_unsupported_capability'
            ),
        };
    }

    public function toOpenAi(string $capability, ResponseInterface $upstreamResponse, RequestContext $ctx): OpenAiResponse
    {
        return match ($capability) {
            PlatformCapability::IMAGES => OpenAiResponse::json(
                $this->normalizeImagePayload($this->jsonBody($upstreamResponse)),
                $upstreamResponse->getStatusCode()
            ),
            PlatformCapability::VIDEOS => OpenAiResponse::json(
                $this->normalizeVideoPayload($this->jsonBody($upstreamResponse), $ctx),
                $upstreamResponse->getStatusCode()
            ),
            default => $this->passthrough($upstreamResponse),
        };
    }

    public function needsAsyncPolling(string $capability, ResponseInterface $submission): bool
    {
        if (!in_array($capability, [PlatformCapability::IMAGES, PlatformCapability::VIDEOS], true)) {
            return false;
        }

        if ($submission->getStatusCode() >= 400) {
            return false;
        }

        $payload = $this->safeJson($submission);

        if ($payload === null) {
            return false;
        }

        $output = is_array($payload['output'] ?? null) ? $payload['output'] : [];
        $results = $output['results'] ?? null;
        $videoUrl = $output['video_url'] ?? $payload['video_url'] ?? null;
        $taskId = $output['task_id'] ?? $payload['task_id'] ?? $this->taskIdFromHeaders($submission);

        return is_string($taskId)
            && $taskId !== ''
            && (!is_array($results) || $results === [])
            && (!is_string($videoUrl) || $videoUrl === '');
    }

    public function resolveAsync(string $capability, ResponseInterface $submission, RequestContext $ctx): OpenAiResponse
    {
        $payload = $this->safeJson($submission) ?? [];
        $output = is_array($payload['output'] ?? null) ? $payload['output'] : [];
        $taskId = $output['task_id'] ?? $payload['task_id'] ?? $this->taskIdFromHeaders($submission);

        if (!is_string($taskId) || $taskId === '') {
            throw ApiException::upstream(
                'Bailian accepted the job but did not return a task ID.',
                'bailian_missing_task_id'
            );
        }

        $task = $this->poller->wait($taskId, $ctx, $this->headers($ctx));

        return match ($capability) {
            PlatformCapability::IMAGES => OpenAiResponse::json($this->normalizeImagePayload($task)),
            PlatformCapability::VIDEOS => OpenAiResponse::json($this->normalizeVideoPayload($task, $ctx)),
            default => throw ApiException::unsupported(
                sprintf('Bailian does not support asynchronous %s.', $capability),
                'bailian_async_unsupported'
            ),
        };
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function imageBody(array $body): array
    {
        $input = $this->compact([
            'prompt' => $body['prompt'] ?? null,
            'negative_prompt' => $body['negative_prompt'] ?? null,
        ]);

        $parameters = $this->compact([
            'size' => isset($body['size']) && is_string($body['size'])
                ? preg_replace('/[xX]/', '*', $body['size'])
                : null,
            'n' => isset($body['n']) && is_numeric($body['n']) ? (int) $body['n'] : null,
            'seed' => $body['seed'] ?? null,
            'prompt_extend' => $body['prompt_extend'] ?? null,
            'watermark' => $body['watermark'] ?? null,
        ]);

        $payload = [
            'model' => $body['model'],
            'input' => $input,
        ];

        if ($parameters !== []) {
            $payload['parameters'] = $parameters;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function videoBody(array $body): array
    {
        $image = $body['image_url'] ?? $body['image'] ?? null;

        if (is_array($image)) {
            $image = $image['url'] ?? $image['image_url'] ?? null;
        }

        $input = $this->compact([
            'prompt' => $body['prompt'] ?? null,
            'img_url' => is_string($image) ? $image : null,
        ]);

        $parameters = $this->compact([
            'duration' => isset($body['duration']) && is_numeric($body['duration']) ? (int) $body['duration'] : null,
            'size' => isset($body['size']) && is_string($body['size'])
                ? preg_replace('/[xX]/', '*', $body['size'])
                : null,
            'resolution' => $body['resolution'] ?? null,
            'seed' => $body['seed'] ?? null,
            'prompt_extend' => $body['prompt_extend'] ?? null,
            'watermark' => $body['watermark'] ?? null,
        ]);

        $payload = [
            'model' => $body['model'],
            'input' => $input,
        ];

        if ($parameters !== []) {
            $payload['parameters'] = $parameters;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeImagePayload(array $payload): array
    {
        $output = is_array($payload['output'] ?? null) ? $payload['output'] : [];
        $results = $output['results'] ?? $payload['data'] ?? [];
        $data = [];

        if (is_array($results)) {
            foreach ($results as $result) {
                if (is_string($result)) {
                    $data[] = ['url' => $result];
                    continue;
                }

                if (!is_array($result)) {
                    continue;
                }

                $item = [];

                if (isset($result['url']) && is_string($result['url'])) {
                    $item['url'] = $result['url'];
                }

                if (isset($result['b64_image']) && is_string($result['b64_image'])) {
                    $item['b64_json'] = $result['b64_image'];
                }

                if (isset($result['b64_json']) && is_string($result['b64_json'])) {
                    $item['b64_json'] = $result['b64_json'];
                }

                if (isset($result['revised_prompt']) && is_string($result['revised_prompt'])) {
                    $item['revised_prompt'] = $result['revised_prompt'];
                }

                if ($item !== []) {
                    $data[] = $item;
                }
            }
        }

        if ($data === []) {
            throw ApiException::upstream(
                'Bailian completed the image task without returning an image.',
                'bailian_missing_image'
            );
        }

        return [
            'created' => $this->createdTimestamp($payload),
            'data' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeVideoPayload(array $payload, RequestContext $ctx): array
    {
        $output = is_array($payload['output'] ?? null) ? $payload['output'] : [];
        $url = $output['video_url'] ?? $payload['video_url'] ?? null;

        if (!is_string($url) || $url === '') {
            $results = $output['results'] ?? $payload['data'] ?? [];

            if (is_array($results)) {
                foreach ($results as $result) {
                    if (is_string($result)) {
                        $url = $result;
                        break;
                    }

                    if (is_array($result) && isset($result['url']) && is_string($result['url'])) {
                        $url = $result['url'];
                        break;
                    }
                }
            }
        }

        if (!is_string($url) || $url === '') {
            throw ApiException::upstream(
                'Bailian completed the video task without returning a video URL.',
                'bailian_missing_video_url'
            );
        }

        $taskId = $output['task_id'] ?? $payload['task_id'] ?? $payload['request_id'] ?? null;

        return [
            'id' => is_string($taskId) ? $taskId : null,
            'object' => 'video',
            'created' => $this->createdTimestamp($payload),
            'model' => $ctx->model,
            'status' => 'completed',
            'data' => [['url' => $url]],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function compact(array $body): array
    {
        return array_filter(
            $body,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeJson(ResponseInterface $response): ?array
    {
        try {
            return $this->jsonBody($response);
        } catch (ApiException) {
            return null;
        }
    }

    private function taskIdFromHeaders(ResponseInterface $response): ?string
    {
        foreach (['X-DashScope-Task-Id', 'X-Task-Id'] as $header) {
            $value = $response->getHeaderLine($header);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
