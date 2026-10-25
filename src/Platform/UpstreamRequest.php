<?php

declare(strict_types=1);

namespace Grotto\Platform;

use Psr\Http\Message\StreamInterface;

final class UpstreamRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $headers = [],
        public readonly string|StreamInterface|null $body = null,
        public readonly bool $stream = false,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public static function json(string $method, string $uri, array $body, array $headers = [], bool $stream = false): self
    {
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new \RuntimeException('Could not encode the upstream request body as JSON.');
        }

        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = $stream ? 'text/event-stream' : ($headers['Accept'] ?? 'application/json');

        return new self($method, $uri, $headers, $encoded, $stream);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function raw(
        string $method,
        string $uri,
        string|StreamInterface|null $body,
        array $headers = [],
        bool $stream = false,
    ): self {
        return new self($method, $uri, $headers, $body, $stream);
    }
}
