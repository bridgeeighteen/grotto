<?php

declare(strict_types=1);

namespace Grotto\Core;

use Grotto\Config\Config;
use Grotto\Platform\OpenAiResponse;
use Grotto\Platform\PlatformCapability;
use Psr\Log\LoggerInterface;

/**
 * Removes EXIF, IPTC, XMP, comments, and timestamp chunks from inline images
 * without decoding or re-encoding pixel data. This is intentionally a binary
 * container editor, not an image processor.
 *
 * URL-only image results cannot be sanitized by the gateway; Grotto reports
 * that scope in /grotto/info instead of silently pretending otherwise.
 */
final class ImageSanitizer
{
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function appliesTo(string $platform, string $capability): bool
    {
        $settings = $this->config->imageSanitizer();

        if (!(bool) ($settings['enabled'] ?? false)) {
            return false;
        }

        $capabilities = array_values(array_unique(array_merge(
            [PlatformCapability::IMAGES],
            (array) ($settings['capabilities'] ?? [])
        )));

        if (!in_array($capability, $capabilities, true)) {
            return false;
        }

        $platforms = (array) ($settings['platforms'] ?? ['*']);

        return in_array('*', $platforms, true) || in_array($platform, $platforms, true);
    }

    public function sanitizeResponse(
        OpenAiResponse $response,
        string $platform,
        string $capability,
        LoggerInterface $logger,
    ): OpenAiResponse {
        if ($response->body instanceof \Psr\Http\Message\StreamInterface || !$this->appliesTo($platform, $capability)) {
            return $response;
        }

        $decoded = json_decode($response->body, true);

        if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
            return $response;
        }

        $changed = 0;

        foreach ($decoded['data'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            if (isset($item['b64_json']) && is_string($item['b64_json'])) {
                $sanitized = $this->sanitizeBase64($item['b64_json']);

                if ($sanitized !== null && $sanitized !== $item['b64_json']) {
                    $decoded['data'][$index]['b64_json'] = $sanitized;
                    $changed++;
                }
            }

            if (isset($item['url']) && is_string($item['url']) && str_starts_with($item['url'], 'data:image/')) {
                $sanitized = $this->sanitizeDataUrl($item['url']);

                if ($sanitized !== null && $sanitized !== $item['url']) {
                    $decoded['data'][$index]['url'] = $sanitized;
                    $changed++;
                }
            }
        }

        if ($changed === 0) {
            return $response;
        }

        $logger->info('Inline image metadata stripped', [
            'platform' => $platform,
            'images' => $changed,
        ]);

        $headers = $response->headers;
        $headers['X-Grotto-Image-Sanitized'] = (string) $changed;

        return OpenAiResponse::json($decoded, $response->status, $headers);
    }

    public function sanitizeBase64(string $value): ?string
    {
        $binary = base64_decode(trim($value), true);

        if ($binary === false) {
            return null;
        }

        $stripped = $this->stripMetadata($binary);

        return $stripped === $binary ? $value : base64_encode($stripped);
    }

    public function sanitizeDataUrl(string $url): ?string
    {
        if (!preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.*)$#is', $url, $matches)) {
            return null;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]) ?? '', true);

        if ($binary === false) {
            return null;
        }

        $stripped = $this->stripMetadata($binary);

        if ($stripped === $binary) {
            return $url;
        }

        return sprintf('data:%s;base64,%s', $matches[1], base64_encode($stripped));
    }

    public function stripMetadata(string $binary): string
    {
        return match (true) {
            str_starts_with($binary, "\xFF\xD8\xFF") => $this->stripJpeg($binary),
            str_starts_with($binary, "\x89PNG\r\n\x1A\n") => $this->stripPng($binary),
            str_starts_with($binary, 'RIFF') && substr($binary, 8, 4) === 'WEBP' => $this->stripWebp($binary),
            default => $binary,
        };
    }

    private function stripJpeg(string $binary): string
    {
        $length = strlen($binary);
        $position = 2;
        $output = "\xFF\xD8";
        $stripMarkers = [
            0xE1, // EXIF / XMP
            0xE3, 0xE4, 0xE5, 0xE6, 0xE7, 0xE8, 0xE9, 0xEA, 0xEB, 0xEC,
            0xED, // IPTC / Photoshop IRB
            0xEF,
            0xFE, // comment
        ];

        while ($position < $length) {
            if (ord($binary[$position]) !== 0xFF) {
                return $binary;
            }

            $segmentStart = $position;

            while ($position < $length && ord($binary[$position]) === 0xFF) {
                $position++;
            }

            if ($position >= $length) {
                return $binary;
            }

            $marker = ord($binary[$position]);
            $position++;

            if ($marker === 0x00) {
                return $binary;
            }

            if ($marker === 0xD9) {
                $output .= "\xFF\xD9";

                return $output . substr($binary, $position);
            }

            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $output .= "\xFF" . chr($marker);
                continue;
            }

            if ($position + 2 > $length) {
                return $binary;
            }

            $segmentLength = (ord($binary[$position]) << 8) | ord($binary[$position + 1]);

            if ($segmentLength < 2 || $position + $segmentLength > $length) {
                return $binary;
            }

            $segmentEnd = $position + $segmentLength;

            if ($marker === 0xDA) {
                // Entropy-coded data begins after the SOS header. Preserve it
                // byte-for-byte; metadata segments can only precede it in the
                // formats Grotto handles.
                return $output . substr($binary, $segmentStart);
            }

            if (!in_array($marker, $stripMarkers, true)) {
                $output .= substr($binary, $segmentStart, $segmentEnd - $segmentStart);
            }

            $position = $segmentEnd;
        }

        return $output;
    }

    private function stripPng(string $binary): string
    {
        $length = strlen($binary);

        if ($length < 8) {
            return $binary;
        }

        $output = substr($binary, 0, 8);
        $position = 8;

        while ($position + 12 <= $length) {
            $chunkLength = unpack('N', substr($binary, $position, 4))[1];

            if ($chunkLength < 0 || $position + 12 + $chunkLength > $length) {
                return $binary;
            }

            $type = substr($binary, $position + 4, 4);
            $total = 12 + $chunkLength;
            $chunk = substr($binary, $position, $total);

            if (!in_array($type, ['eXIf', 'tEXt', 'zTXt', 'iTXt', 'tIME'], true)) {
                $output .= $chunk;
            }

            $position += $total;

            if ($type === 'IEND') {
                return $output . substr($binary, $position);
            }
        }

        return $binary;
    }

    private function stripWebp(string $binary): string
    {
        $length = strlen($binary);

        if ($length < 12) {
            return $binary;
        }

        $position = 12;
        $chunks = '';

        while ($position + 8 <= $length) {
            $fourcc = substr($binary, $position, 4);
            $chunkLength = unpack('V', substr($binary, $position + 4, 4))[1];
            $total = 8 + $chunkLength + ($chunkLength % 2);

            if ($position + $total > $length) {
                return $binary;
            }

            if (!in_array($fourcc, ['EXIF', 'XMP '], true)) {
                $chunks .= substr($binary, $position, $total);
            }

            $position += $total;
        }

        if ($position !== $length) {
            return $binary;
        }

        return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
    }
}
