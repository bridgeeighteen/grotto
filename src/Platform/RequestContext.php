<?php

declare(strict_types=1);

namespace Grotto\Platform;

final class RequestContext
{
    /**
     * @param array<string, string> $routeArgs
     */
    public function __construct(
        public readonly string $platform,
        public readonly string $capability,
        public readonly string $path,
        public readonly string $requestId,
        public readonly string $clientId,
        public readonly bool $stream = false,
        public readonly ?string $region = null,
        public readonly ?string $workspaceId = null,
        public readonly bool $customEndpoint = false,
        public readonly string $contentType = 'application/json',
        public readonly string $rawBody = '',
        public readonly ?string $model = null,
        public readonly array $routeArgs = [],
        #[\SensitiveParameter]
        public readonly string $apiKey = '',
    ) {
    }

    public function isCustomBailian(): bool
    {
        return $this->platform === 'bailian' && $this->customEndpoint;
    }

    public function effectiveRegion(): ?string
    {
        return $this->region;
    }
}
