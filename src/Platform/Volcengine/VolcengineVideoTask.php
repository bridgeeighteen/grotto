<?php

declare(strict_types=1);

namespace Grotto\Platform\Volcengine;

use Grotto\Config\Config;
use Grotto\Core\Exception\ApiException;
use Grotto\Core\UpstreamErrorMapper;
use Grotto\Platform\AbstractAsyncTask;
use Grotto\Platform\OpenAiResponse;
use Grotto\Platform\RequestContext;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

final class VolcengineVideoTask extends AbstractAsyncTask
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
            rtrim($this->baseUrl, '/') . '/contents/generations/tasks/' . rawurlencode($taskId),
            $headers + ['Accept' => 'application/json'],
            'volcengine',
            $ctx,
            static function (array $payload): array {
                $status = strtolower((string) ($payload['status'] ?? $payload['task_status'] ?? ''));
                $url = $payload['content']['video_url'] ?? $payload['video_url'] ?? null;

                if (in_array($status, ['failed', 'cancelled', 'canceled', 'expired'], true)) {
                    $reason = $payload['error']['message']
                        ?? $payload['error']
                        ?? $payload['message']
                        ?? 'Volcengine Ark reported a failed video task.';

                    return ['state' => 'failed', 'reason' => (string) $reason];
                }

                if ($status === 'succeeded' || (is_string($url) && $url !== '')) {
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
        $url = $task['content']['video_url'] ?? $task['video_url'] ?? null;

        if ((!is_string($url) || $url === '') && isset($task['data']) && is_array($task['data'])) {
            foreach ($task['data'] as $item) {
                if (is_string($item)) {
                    $url = $item;
                    break;
                }

                if (is_array($item) && isset($item['url']) && is_string($item['url'])) {
                    $url = $item['url'];
                    break;
                }
            }
        }

        if (!is_string($url) || $url === '') {
            throw ApiException::upstream(
                'Volcengine Ark completed the video task without returning a video URL.',
                'volcengine_missing_video_url'
            );
        }

        $taskId = $task['id'] ?? $task['task_id'] ?? null;

        return OpenAiResponse::json([
            'id' => is_string($taskId) ? $taskId : null,
            'object' => 'video',
            'created' => isset($task['created_at']) && is_numeric($task['created_at'])
                ? (int) $task['created_at']
                : time(),
            'model' => $ctx->model,
            'status' => 'completed',
            'data' => [['url' => $url]],
        ]);
    }
}
