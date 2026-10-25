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

final class ModelScopeImageTask extends AbstractAsyncTask
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
                'X-ModelScope-Task-Type' => 'image_generation',
            ],
            'modelscope',
            $ctx,
            static function (array $payload): array {
                $status = strtoupper((string) ($payload['task_status'] ?? $payload['status'] ?? ''));
                $images = $payload['output_images'] ?? $payload['output']['images'] ?? null;

                if (in_array($status, ['FAILED', 'CANCELED', 'CANCELLED', 'UNKNOWN'], true)) {
                    return ['state' => 'failed', 'reason' => self::failureReason($payload)];
                }

                if (in_array($status, ['SUCCEED', 'SUCCEEDED', 'SUCCESS'], true)
                    || (is_array($images) && $images !== [])) {
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
        $images = $task['output_images'] ?? $task['output']['images'] ?? $task['data'] ?? [];
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
                'ModelScope completed the image task without returning an image.',
                'modelscope_missing_image'
            );
        }

        unset($ctx);

        return OpenAiResponse::json([
            'created' => isset($task['created_at']) && is_numeric($task['created_at'])
                ? (int) $task['created_at']
                : time(),
            'data' => $data,
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

        return is_string($error) && $error !== '' ? $error : 'ModelScope reported a failed image task.';
    }
}
