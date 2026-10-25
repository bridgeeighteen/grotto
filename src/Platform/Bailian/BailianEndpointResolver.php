<?php

declare(strict_types=1);

namespace Grotto\Platform\Bailian;

use Grotto\Core\Exception\ApiException;
use Grotto\Platform\RequestContext;
use Grotto\Support\Env;

/**
 * Maps Bailian's standard and workspace-scoped domains.
 *
 * Standard:  https://dashscope.aliyuncs.com/compatible-mode/v1
 * Workspace: https://{workspaceId}.{region}.maas.aliyuncs.com/compatible-mode/v1
 */
final class BailianEndpointResolver
{
    /**
     * @param array<string, mixed> $platform
     */
    public function __construct(
        private readonly array $platform,
    ) {
    }

    public function compatibleBase(RequestContext $ctx): string
    {
        if (!$ctx->customEndpoint) {
            return $this->standardOrigin() . '/compatible-mode/v1';
        }

        return $this->customOrigin($ctx) . '/compatible-mode/v1';
    }

    public function nativeBase(RequestContext $ctx): string
    {
        if (!$ctx->customEndpoint) {
            return $this->standardOrigin() . '/api/v1';
        }

        return $this->customOrigin($ctx) . '/api/v1';
    }

    public function compatibleUrl(RequestContext $ctx, string $path): string
    {
        return $this->compatibleBase($ctx) . '/' . ltrim($path, '/');
    }

    public function nativeUrl(RequestContext $ctx, string $path): string
    {
        return $this->nativeBase($ctx) . '/' . ltrim($path, '/');
    }

    public function customOrigin(RequestContext $ctx): string
    {
        $region = $this->validateRegion((string) $ctx->region);
        $workspaceId = $this->validateWorkspaceId((string) $ctx->workspaceId);

        return sprintf('https://%s.%s.maas.aliyuncs.com', $workspaceId, $region);
    }

    public function standardOrigin(): string
    {
        $baseUrl = (string) ($this->platform['base_url'] ?? 'https://dashscope.aliyuncs.com');
        $parts = parse_url($baseUrl);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw ApiException::serviceUnavailable(
                'The configured Bailian base URL is invalid.',
                'bailian_invalid_base_url'
            );
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw ApiException::serviceUnavailable(
                'The configured Bailian base URL must use HTTP or HTTPS.',
                'bailian_invalid_base_url'
            );
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }

    private function validateRegion(string $region): string
    {
        if (!preg_match('/^[a-z]{2}(?:-[a-z0-9]+)+$/', $region)) {
            throw ApiException::invalidRequest(
                'The Bailian region must look like "cn-beijing" or "ap-southeast-1".',
                'bailian_invalid_region',
                'region'
            );
        }

        $allowed = Env::list('BAILIAN_ALLOWED_REGIONS');

        if ($allowed !== [] && !in_array($region, $allowed, true)) {
            throw ApiException::invalidRequest(
                sprintf('The region "%s" is not allowed by this Grotto instance.', $region),
                'bailian_region_not_allowed',
                'region'
            );
        }

        return $region;
    }

    private function validateWorkspaceId(string $workspaceId): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $workspaceId)) {
            throw ApiException::invalidRequest(
                'The Bailian workspace ID contains unsupported characters.',
                'bailian_invalid_workspace',
                'workspaceId'
            );
        }

        return $workspaceId;
    }
}
