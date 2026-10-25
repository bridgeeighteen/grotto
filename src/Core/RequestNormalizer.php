<?php

declare(strict_types=1);

namespace Grotto\Core;

use Grotto\Core\Exception\ApiException;
use Grotto\Platform\PlatformCapability;
use Psr\Http\Message\ServerRequestInterface;

final class RequestNormalizer
{
    /**
     * Parse and lightly validate an incoming OpenAI request.
     *
     * Message contents and prompts are deliberately not logged or copied into
     * error messages.
     *
     * @return array<string, mixed>
     */
    public function normalize(ServerRequestInterface $request, string $capability): array
    {
        if ($capability === PlatformCapability::STT) {
            $this->assertAudioUpload($request);

            return [];
        }

        if ($capability === PlatformCapability::MODELS) {
            return [];
        }

        $raw = (string) $request->getBody();
        $body = $this->decodeJson($raw);

        $this->requireModel($body);

        switch ($capability) {
            case PlatformCapability::CHAT:
                $this->assertChat($body);
                break;
            case PlatformCapability::IMAGES:
                $this->assertPrompt($body, 'prompt');
                break;
            case PlatformCapability::VIDEOS:
                $this->assertVideo($body);
                break;
            case PlatformCapability::TTS:
                $this->assertTts($body);
                break;
        }

        if (($body['stream'] ?? false) === true && $capability !== PlatformCapability::CHAT) {
            throw ApiException::invalidRequest(
                'Streaming is only available for chat completions.',
                'stream_not_supported',
                'stream'
            );
        }

        if (isset($body['stream']) && !is_bool($body['stream'])) {
            throw ApiException::invalidRequest('The "stream" field must be a boolean.', 'invalid_stream', 'stream');
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw): array
    {
        if (trim($raw) === '') {
            throw ApiException::invalidRequest('The request body is empty.', 'invalid_json');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ApiException::invalidRequest('The request body is not valid JSON.', 'invalid_json');
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw ApiException::invalidRequest('The request body must be a JSON object.', 'invalid_json');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requireModel(array $body): void
    {
        if (!isset($body['model']) || !is_string($body['model']) || trim($body['model']) === '') {
            throw ApiException::invalidRequest(
                'A non-empty "model" field is required.',
                'missing_model',
                'model'
            );
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function assertChat(array $body): void
    {
        if (!isset($body['messages']) || !is_array($body['messages']) || $body['messages'] === []) {
            throw ApiException::invalidRequest(
                '"messages" must be a non-empty array.',
                'invalid_messages',
                'messages'
            );
        }

        foreach ($body['messages'] as $index => $message) {
            if (!is_array($message) || !isset($message['role']) || !is_string($message['role'])) {
                throw ApiException::invalidRequest(
                    sprintf('The message at index %d must have a string "role".', $index),
                    'invalid_messages',
                    'messages'
                );
            }

            if (!array_key_exists('content', $message)) {
                throw ApiException::invalidRequest(
                    sprintf('The message at index %d must include "content".', $index),
                    'invalid_messages',
                    'messages'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function assertPrompt(array $body, string $field): void
    {
        if (!isset($body[$field]) || !is_string($body[$field]) || trim($body[$field]) === '') {
            throw ApiException::invalidRequest(
                sprintf('A non-empty "%s" field is required.', $field),
                'missing_' . $field,
                $field
            );
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function assertVideo(array $body): void
    {
        $hasPrompt = isset($body['prompt']) && is_string($body['prompt']) && trim($body['prompt']) !== '';
        $hasImage = isset($body['image']) || isset($body['image_url']);

        if (!$hasPrompt && !$hasImage) {
            throw ApiException::invalidRequest(
                'Video generation requires a "prompt" or an input image.',
                'missing_video_input',
                'prompt'
            );
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function assertTts(array $body): void
    {
        if (!isset($body['input']) || !is_string($body['input']) || trim($body['input']) === '') {
            throw ApiException::invalidRequest(
                'A non-empty "input" field is required for speech generation.',
                'missing_input',
                'input'
            );
        }
    }

    private function assertAudioUpload(ServerRequestInterface $request): void
    {
        $contentType = strtolower($request->getHeaderLine('Content-Type'));

        if (!str_starts_with($contentType, 'multipart/form-data')) {
            throw ApiException::invalidRequest(
                'Audio transcription requests must use multipart/form-data.',
                'invalid_content_type',
                'Content-Type'
            );
        }
    }
}
