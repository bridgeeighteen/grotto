<?php

declare(strict_types=1);

namespace Grotto\Platform\Bailian;

use Grotto\Config\Config;
use Grotto\Core\UpstreamErrorMapper;
use Grotto\Platform\AbstractAsyncTask;
use Grotto\Platform\RequestContext;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Polls Bailian's native task endpoint for image and video jobs.
 */
final class BailianAsyncPoller extends AbstractAsyncTask
{
    public function __construct(
        ClientInterface $http,
        Config $config,
        UpstreamErrorMapper $errors,
        LoggerInterface $logger,
        private readonly BailianEndpointResolver $resolver,
    ) {
        parent::__construct($http, $config, $errors, $logger);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function wait(string $taskId, RequestContext $ctx, array $headers): array
    {
        $url = $this->resolver->nativeUrl($ctx, 'tasks/' . rawurlencode($taskId));

        return $this->pollUntilTerminal(
            $url,
            $headers + ['Accept' => 'application/json'],
            'bailian',
            $ctx,
            static function (array $payload): array {
                $output = is_array($payload['output'] ?? null) ? $payload['output'] : [];
                $status = strtoupper((string) ($output['task_status'] ?? $payload['task_status'] ?? ''));

                if (in_array($status, ['FAILED', 'CANCELED', 'CANCELLED', 'UNKNOWN'], true)) {
                    $reason = $output['message']
                        ?? $payload['message']
                        ?? $output['code']
                        ?? $payload['code']
                        ?? 'Bailian reported a failed task.';

                    return ['state' => 'failed', 'reason' => (string) $reason];
                }

                $hasResults = is_array($output['results'] ?? null) && $output['results'] !== [];

                if (in_array($status, ['SUCCEEDED', 'SUCCESS', 'SUCCEED'], true) || $hasResults) {
                    return ['state' => 'succeeded'];
                }

                return ['state' => 'pending'];
            }
        );
    }
}
