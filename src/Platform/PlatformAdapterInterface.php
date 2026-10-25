<?php

declare(strict_types=1);

namespace Grotto\Platform;

use Psr\Http\Message\ResponseInterface;

interface PlatformAdapterInterface
{
    public function id(): string;

    /**
     * Convert an OpenAI-shaped body into a platform-shaped upstream request.
     *
     * @param array<string, mixed> $openAiBody
     */
    public function toUpstream(string $capability, array $openAiBody, RequestContext $ctx): UpstreamRequest;

    /**
     * Convert a terminal upstream response into an OpenAI-shaped response.
     */
    public function toOpenAi(string $capability, ResponseInterface $upstreamResponse, RequestContext $ctx): OpenAiResponse;

    public function supports(string $capability): bool;

    /**
     * @return list<string>
     */
    public function capabilities(): array;

    /**
     * True when the submission created an asynchronous task that must be polled.
     */
    public function needsAsyncPolling(string $capability, ResponseInterface $submission): bool;

    /**
     * Poll an asynchronous task until it reaches a terminal state.
     */
    public function resolveAsync(string $capability, ResponseInterface $submission, RequestContext $ctx): OpenAiResponse;
}
