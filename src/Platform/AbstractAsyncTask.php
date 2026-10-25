<?php

declare(strict_types=1);

namespace Grotto\Platform;

use Grotto\Config\Config;
use Grotto\Core\Exception\ApiException;
use Grotto\Core\UpstreamErrorMapper;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AbstractAsyncTask
{
    public function __construct(
        protected readonly ClientInterface $http,
        protected readonly Config $config,
        protected readonly UpstreamErrorMapper $errors,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Poll a task endpoint until the classifier reports a terminal state.
     *
     * The classifier receives the decoded task payload and must return one of:
     * ['state' => 'pending'|'succeeded'|'failed', 'reason' => string|null].
     *
     * @param array<string, string> $headers
     * @param callable(array<string, mixed>): array{state: string, reason?: string|null} $classify
     * @return array<string, mixed>
     */
    protected function pollUntilTerminal(
        string $url,
        array $headers,
        string $platform,
        RequestContext $ctx,
        callable $classify,
    ): array {
        $settings = $this->config->async();
        $started = microtime(true);
        $attempts = 0;

        while (true) {
            $attempts++;
            $elapsed = microtime(true) - $started;

            if ($attempts > $settings['max_attempts'] || $elapsed > $settings['timeout_seconds']) {
                throw $this->errors->timeout($platform, $ctx);
            }

            try {
                $response = $this->http->request('GET', $url, [
                    'headers' => $headers,
                    'http_errors' => false,
                ]);
            } catch (Throwable $exception) {
                throw $this->errors->fromThrowable($exception, $platform, $ctx);
            }

            if ($response->getStatusCode() >= 400) {
                throw $this->errors->fromResponse($response, $platform, $ctx);
            }

            $payload = json_decode((string) $response->getBody(), true);

            if (!is_array($payload)) {
                throw ApiException::upstream(
                    sprintf('%s returned an unreadable task status.', $this->platformLabel($platform)),
                    $platform . '_invalid_task_response'
                );
            }

            $result = $classify($payload);
            $state = (string) ($result['state'] ?? 'pending');

            if ($state === 'succeeded') {
                $this->logger->info('Upstream asynchronous task completed', [
                    'platform' => $platform,
                    'request_id' => $ctx->requestId,
                    'capability' => $ctx->capability,
                    'attempts' => $attempts,
                    'elapsed_ms' => (int) round($elapsed * 1000),
                ]);

                return $payload;
            }

            if ($state === 'failed') {
                throw $this->errors->taskFailed(
                    $platform,
                    (string) ($result['reason'] ?? 'Unknown task failure.'),
                    $ctx,
                    $payload
                );
            }

            $this->logger->debug('Waiting on upstream asynchronous task', [
                'platform' => $platform,
                'request_id' => $ctx->requestId,
                'attempt' => $attempts,
            ]);

            $this->pause((int) $settings['poll_interval_ms']);
        }
    }

    protected function pause(int $milliseconds): void
    {
        usleep(max(1, $milliseconds) * 1000);
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
