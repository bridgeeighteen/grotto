<?php

declare(strict_types=1);

namespace Grotto\Tests\Core;

use Grotto\Core\SystemPromptInjector;
use Grotto\Platform\PlatformCapability;
use Grotto\Tests\TestCase;

final class SystemPromptInjectorTest extends TestCase
{
    public function testInjectsPromptWhenNoSystemMessageExists(): void
    {
        $injector = new SystemPromptInjector($this->config([
            'GROTTO_SYSTEM_PROMPT' => 'Use the instance policy.',
        ]));

        $body = $injector->apply(PlatformCapability::CHAT, [
            'model' => 'test',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ]);

        self::assertSame('system', $body['messages'][0]['role']);
        self::assertSame('Use the instance policy.', $body['messages'][0]['content']);
        self::assertCount(2, $body['messages']);
    }

    public function testReplacesLowInformationSystemPrompt(): void
    {
        $injector = new SystemPromptInjector($this->config([
            'GROTTO_SYSTEM_PROMPT' => 'Use the instance policy.',
        ]));

        $body = $injector->apply(PlatformCapability::CHAT, [
            'model' => 'test',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        self::assertSame('Use the instance policy.', $body['messages'][0]['content']);
        self::assertCount(2, $body['messages']);
    }

    public function testCollapsesMultipleLowInformationSystemMessages(): void
    {
        $injector = new SystemPromptInjector($this->config([
            'GROTTO_SYSTEM_PROMPT' => 'Use the instance policy.',
        ]));

        $body = $injector->apply(PlatformCapability::CHAT, [
            'model' => 'test',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'system', 'content' => 'You are a large language model.'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        self::assertCount(2, $body['messages']);
        self::assertSame('Use the instance policy.', $body['messages'][0]['content']);
        self::assertSame('user', $body['messages'][1]['role']);
    }

    public function testPreservesExplicitSystemPrompt(): void
    {
        $injector = new SystemPromptInjector($this->config([
            'GROTTO_SYSTEM_PROMPT' => 'Use the instance policy.',
        ]));

        $body = $injector->apply(PlatformCapability::CHAT, [
            'model' => 'test',
            'messages' => [
                ['role' => 'system', 'content' => 'Answer exclusively in French.'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        self::assertSame('Answer exclusively in French.', $body['messages'][0]['content']);
    }

    public function testDoesNotTouchNonChatCapabilities(): void
    {
        $injector = new SystemPromptInjector($this->config());
        $original = ['model' => 'test', 'prompt' => 'A grotto'];
        $body = $injector->apply(PlatformCapability::IMAGES, $original);

        self::assertSame($original, $body);
    }

    public function testDisabledInjectionLeavesBodyUntouched(): void
    {
        $injector = new SystemPromptInjector($this->config([
            'GROTTO_SYSTEM_PROMPT_ENABLED' => 'false',
        ]));

        $original = [
            'model' => 'test',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ];
        $body = $injector->apply(PlatformCapability::CHAT, $original);

        self::assertSame($original, $body);
    }
}
