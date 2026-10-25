<?php

declare(strict_types=1);

namespace Grotto\Platform\ModelScope;

use Grotto\Config\Config;
use Grotto\Core\Exception\ApiException;
use Grotto\Core\UpstreamErrorMapper;
use Grotto\Platform\AbstractAsyncTask;
use Grotto\Platform\OpenAiResponse;
use Grotto\Platform\RequestContext;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

final class ModelScopeVideoTask extends AbstractAsyncTask
{
    public function __construct(
        ClientInterface $http,
        Config $config,
        UpstreamErrorMapper $errors,
        LoggerInterface $logger,
        private readonly string $baseUrl,
    ) {
        parent::__construct($http, $config, $errors, $logger);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function wait(string $taskId, RequestContext $ctx, array $headers): array
    {
        return $this->pollUntilTerminal(
            rtrim($this->baseUrl, '/') . '/tasks/' . rawurlencode($taskId),
            $headers + [
                'Accept' => 'application/json',
                'X-ModelScope-Task-Type' => 'video_generation',
            ],
            'modelscope',
            $ctx,
            static function (array $payload): array {
                $status = strtoupper((string) ($payload['task_status'] ?? $payload['status'] ?? ''));
                $videos = $payload['output_videos'] ?? $payload['output']['videos'] ?? null;

                if (in_array($status, ['FAILED', 'CANCELED', 'CANCELLED', 'UNKNOWN'], true)) {
                    return ['state' => 'failed', 'reason' => self::failureReason($payload)];
                }

                if (in_array($status, ['SUCCEED', 'SUCCEEDED', 'SUCCESS'], true)
                    || (is_array($videos) && $videos !== [])) {
                    return ['state' => 'succeeded'];
                }

                return ['state' => 'pending'];
            }
        );
    }

    /**
     * @param array<string, mixed> $task
     */
    public function toOpenAi(array $task, RequestContext $ctx): OpenAiResponse
    {
        $videos = $task['output_videos'] ?? $task['output']['videos'] ?? null;
        $url = $task['output']['video_url'] ?? $task['video_url'] ?? null;

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
                'ModelScope completed the video task without returning a video URL.',
                'modelscope_missing_video'
            );
        }

        $taskId = $task['task_id'] ?? null;
        $created = isset($task['created_at']) && is_numeric($task['created_at'])
            ? (int) $task['created_at']
            : time();

        return OpenAiResponse::json([
            'id' => is_string($taskId) ? $taskId : null,
            'object' => 'video',
            'created' => $created,
            'model' => $ctx->model,
            'status' => 'completed',
            'data' => [['url' => $url]],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function failureReason(array $payload): string
    {
        $error = $payload['error'] ?? $payload['errors'] ?? $payload['message'] ?? null;

        if (is_array($error)) {
            $error = $error['message'] ?? $error[0]['message'] ?? json_encode($error, JSON_UNESCAPED_SLASHES);
        }

        return is_string($error) && $error !== '' ? $error : 'ModelScope reported a failed video task.';
    }
}
