<?php

declare(strict_types=1);

namespace Grotto\Tests\Platform;

use Grotto\Core\Exception\ApiException;
use Grotto\Platform\Bailian\BailianEndpointResolver;
use Grotto\Platform\RequestContext;
use Grotto\Tests\TestCase;

final class BailianEndpointResolverTest extends TestCase
{
    public function testStandardCompatibleEndpointIgnoresConfiguredPath(): void
    {
        $config = $this->config([
            'BAILIAN_BASE_URL' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
        ]);
        $resolver = new BailianEndpointResolver($config->platform('bailian'));

        self::assertSame(
            'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
            $resolver->compatibleBase($this->context())
        );
        self::assertSame(
            'https://dashscope-intl.aliyuncs.com/api/v1/tasks/abc',
            $resolver->nativeUrl($this->context(), 'tasks/abc')
        );
    }

    public function testWorkspaceEndpointUsesRegionAndWorkspaceSubdomain(): void
    {
        $config = $this->config();
        $resolver = new BailianEndpointResolver($config->platform('bailian'));
        $ctx = $this->context('cn-beijing', 'ws_abc123', true);

        self::assertSame(
            'https://ws_abc123.cn-beijing.maas.aliyuncs.com/compatible-mode/v1',
            $resolver->compatibleBase($ctx)
        );
        self::assertSame(
            'https://ws_abc123.ap-southeast-1.maas.aliyuncs.com/compatible-mode/v1',
            $resolver->compatibleBase($this->context('ap-southeast-1', 'ws_abc123', true))
        );
    }

    public function testRegionIsPartOfTheWorkspaceDomain(): void
    {
        $config = $this->config();
        $resolver = new BailianEndpointResolver($config->platform('bailian'));

        self::assertSame(
            'https://ws_abc.ap-southeast-1.maas.aliyuncs.com/compatible-mode/v1',
            $resolver->compatibleBase($this->context('ap-southeast-1', 'ws_abc', true))
        );
    }

    public function testRejectsUnsafeWorkspaceId(): void
    {
        $config = $this->config();
        $resolver = new BailianEndpointResolver($config->platform('bailian'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('workspace ID');

        $resolver->compatibleBase($this->context('cn-beijing', 'evil.example.com', true));
    }

    public function testRejectsUnsafeRegion(): void
    {
        $config = $this->config();
        $resolver = new BailianEndpointResolver($config->platform('bailian'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('region must look like');

        $resolver->compatibleBase($this->context('cn-beijing/evil', 'ws', true));
    }

    public function testHonoursAllowedRegionList(): void
    {
        $config = $this->config([
            'BAILIAN_ALLOWED_REGIONS' => 'cn-beijing',
        ]);
        $resolver = new BailianEndpointResolver($config->platform('bailian'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('not allowed');

        $resolver->compatibleBase($this->context('ap-southeast-1', 'ws', true));
    }

    private function context(
        string $region = '',
        string $workspace = '',
        bool $custom = false,
    ): RequestContext {
        return new RequestContext(
            platform: 'bailian',
            capability: 'chat',
            path: 'chat/completions',
            requestId: 'req_test',
            clientId: 'client',
            stream: false,
            region: $region,
            workspaceId: $workspace,
            customEndpoint: $custom,
        );
    }
}
