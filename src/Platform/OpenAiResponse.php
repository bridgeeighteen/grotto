<?php

declare(strict_types=1);

namespace Grotto\Platform;

use Psr\Http\Message\StreamInterface;

final class OpenAiResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string|StreamInterface $body,
        public readonly array $headers = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new \RuntimeException('Could not encode the OpenAI response as JSON.');
        }

        $headers['Content-Type'] = 'application/json; charset=utf-8';

        return new self($status, $encoded, $headers);
    }
}
