<?php

declare(strict_types=1);

namespace Grotto\Tests\Config;

use Grotto\Tests\TestCase;

final class ConfigTest extends TestCase
{
    public function testPlatformMetadataIsAvailableWithoutStoredKeys(): void
    {
        $config = $this->config();

        $bailian = $config->platform('bailian');

        self::assertTrue($bailian['enabled']);
        self::assertContains('chat', $bailian['capabilities']);
        self::assertSame('https://dashscope.aliyuncs.com', $bailian['base_url']);
        self::assertArrayNotHasKey('api_key', $bailian);
        self::assertArrayNotHasKey('api_key_env', $bailian);
        self::assertArrayNotHasKey('configured', $bailian);
    }

    public function testApiKeyPolicyComesFromTheClientRequest(): void
    {
        $config = $this->config();

        self::assertSame([
            'header' => 'Authorization',
            'scheme' => 'Bearer',
            'source' => 'request',
            'stored' => false,
        ], $config->apiKeyPolicy());
        self::assertSame(['en', 'zh-CN'], $config->supportedLocales());
    }

    public function testModelscopeAudioCanBeEnabledExplicitly(): void
    {
        $disabled = $this->config(['MODELSCOPE_AUDIO_ENABLED' => 'false']);
        $enabled = $this->config(['MODELSCOPE_AUDIO_ENABLED' => 'true']);

        self::assertNotContains('tts', $disabled->platform('modelscope')['capabilities']);
        self::assertContains('tts', $enabled->platform('modelscope')['capabilities']);
    }

    public function testTrustedProxiesCanBeConfigured(): void
    {
        $config = $this->config([
            'GROTTO_TRUSTED_PROXIES' => '10.0.0.0/8, 2001:db8::1, 192.0.2.10',
        ]);

        self::assertSame(
            ['10.0.0.0/8', '2001:db8::1', '192.0.2.10'],
            $config->trustedProxies()
        );
    }

    public function testPromptPolicyCanBeOverridden(): void
    {
        $config = $this->config([
            'GROTTO_SYSTEM_PROMPT' => 'Custom policy prompt.',
            'GROTTO_SYSTEM_PROMPT_ENABLED' => 'true',
            'GROTTO_EXPOSE_SYSTEM_PROMPT' => 'false',
        ]);

        self::assertSame('Custom policy prompt.', $config->systemPrompt());
        self::assertTrue($config->systemPromptEnabled());
        self::assertFalse($config->systemPromptExposed());
    }
}
