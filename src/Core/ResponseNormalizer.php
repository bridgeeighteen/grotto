<?php

declare(strict_types=1);

namespace Grotto\Core;

use Grotto\Platform\OpenAiResponse;
use Grotto\Platform\PlatformCapability;
use Psr\Http\Message\StreamInterface;

final class ResponseNormalizer
{
    public function normalize(OpenAiResponse $response, string $capability): OpenAiResponse
    {
        if ($response->body instanceof StreamInterface) {
            return $response;
        }

        $contentType = strtolower($response->headers['Content-Type'] ?? '');

        if (!str_contains($contentType, 'json') && !$this->looksLikeJson($response->body)) {
            return $response;
        }

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $response;
        }

        if (!is_array($decoded)) {
            return $response;
        }

        $decoded = match ($capability) {
            PlatformCapability::MODELS => $this->normalizeModels($decoded),
            PlatformCapability::CHAT => $this->normalizeChat($decoded),
            default => $decoded,
        };

        return OpenAiResponse::json($decoded, $response->status, $response->headers);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeModels(array $payload): array
    {
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $models = [];

        foreach ($data as $model) {
            if (is_string($model)) {
                $model = ['id' => $model];
            }

            if (!is_array($model) || !isset($model['id']) || !is_string($model['id'])) {
                continue;
            }

            $models[] = [
                'id' => $model['id'],
                'object' => 'model',
                'created' => isset($model['created']) && is_numeric($model['created'])
                    ? (int) $model['created']
                    : 0,
                'owned_by' => isset($model['owned_by']) && is_string($model['owned_by'])
                    ? $model['owned_by']
                    : 'grotto',
            ];
        }

        return [
            'object' => 'list',
            'data' => $models,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeChat(array $payload): array
    {
        if (isset($payload['choices']) && is_array($payload['choices']) && !isset($payload['object'])) {
            $payload['object'] = 'chat.completion';
        }

        if (isset($payload['choices']) && is_array($payload['choices']) && !isset($payload['created'])) {
            $payload['created'] = time();
        }

        return $payload;
    }

    private function looksLikeJson(string $body): bool
    {
        $trimmed = ltrim($body);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }
}
