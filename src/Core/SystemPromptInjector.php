<?php

declare(strict_types=1);

namespace Grotto\Core;

use Grotto\Config\Config;
use Grotto\Platform\PlatformCapability;

/**
 * Applies the instance-level system prompt contract before a request reaches a
 * platform adapter. Only explicit, non-default system messages survive.
 */
final class SystemPromptInjector
{
    /** @var list<string> */
    private array $lowInformationPrompts;

    public function __construct(
        private readonly Config $config,
    ) {
        $this->lowInformationPrompts = array_map(
            fn (string $prompt): string => $this->normalize($prompt),
            array_filter($config->lowInformationSystemPrompts(), 'is_string')
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function apply(string $capability, array $body): array
    {
        if ($capability !== PlatformCapability::CHAT || !$this->config->systemPromptEnabled()) {
            return $body;
        }

        $prompt = $this->config->systemPrompt();

        if ($prompt === '' || !isset($body['messages']) || !is_array($body['messages'])) {
            return $body;
        }

        $messages = array_values($body['messages']);
        $systemIndexes = [];

        foreach ($messages as $index => $message) {
            if (is_array($message) && ($message['role'] ?? null) === 'system') {
                $systemIndexes[] = $index;
            }
        }

        if ($systemIndexes === []) {
            array_unshift($messages, ['role' => 'system', 'content' => $prompt]);
            $body['messages'] = $messages;

            return $body;
        }

        $allLowInformation = true;

        foreach ($systemIndexes as $index) {
            $content = $this->contentAsString($messages[$index]['content'] ?? null);

            if (!$this->isLowInformation($content)) {
                $allLowInformation = false;
                break;
            }
        }

        if (!$allLowInformation) {
            return $body;
        }

        $first = array_shift($systemIndexes);
        $messages[$first]['content'] = $prompt;

        foreach ($systemIndexes as $index) {
            unset($messages[$index]);
        }

        $body['messages'] = array_values($messages);

        return $body;
    }

    private function isLowInformation(string $content): bool
    {
        return in_array($this->normalize($content), $this->lowInformationPrompts, true);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return rtrim($value, " \t\n\r\0\x0B.!?。！？");
    }

    private function contentAsString(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (!is_array($content)) {
            return '';
        }

        $parts = [];

        foreach ($content as $part) {
            if (is_array($part) && ($part['type'] ?? null) === 'text' && isset($part['text'])) {
                $parts[] = (string) $part['text'];
            }
        }

        return implode("\n", $parts);
    }
}
