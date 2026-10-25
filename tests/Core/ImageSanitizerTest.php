<?php

declare(strict_types=1);

namespace Grotto\Tests\Core;

use Grotto\Core\ImageSanitizer;
use Grotto\Platform\OpenAiResponse;
use Grotto\Platform\PlatformCapability;
use Grotto\Tests\TestCase;
use Psr\Log\NullLogger;

final class ImageSanitizerTest extends TestCase
{
    public function testStripsJpegMetadataWithoutTouchingTheScan(): void
    {
        $exif = "Exif\0\0GPS Device Secret";
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
        $scanHeader = "\x01\x02\x03\x00";
        $scan = "\x12\x34\x56\x78";
        $jpeg = "\xFF\xD8" . $app1 . "\xFF\xDA" . pack('n', strlen($scanHeader) + 2) . $scanHeader . $scan . "\xFF\xD9";

        $sanitizer = $this->sanitizer();
        $clean = $sanitizer->stripMetadata($jpeg);

        self::assertStringStartsWith("\xFF\xD8", $clean);
        self::assertStringNotContainsString('GPS Device Secret', $clean);
        self::assertStringContainsString($scan, $clean);
        self::assertStringEndsWith("\xFF\xD9", $clean);
    }

    public function testStripsPngTextExifAndTimestampChunks(): void
    {
        $png = "\x89PNG\r\n\x1A\n"
            . $this->pngChunk('IHDR', str_repeat("\x00", 13))
            . $this->pngChunk('tEXt', "Software\x00Grotto Test")
            . $this->pngChunk('eXIf', "Exif metadata")
            . $this->pngChunk('tIME', "\x07\xE8\x01\x02\x03\x04\x05")
            . $this->pngChunk('IDAT', "pixel-data")
            . $this->pngChunk('IEND', '');

        $clean = $this->sanitizer()->stripMetadata($png);

        self::assertStringStartsWith("\x89PNG\r\n\x1A\n", $clean);
        self::assertStringNotContainsString('Software', $clean);
        self::assertStringNotContainsString('Exif metadata', $clean);
        self::assertStringNotContainsString('tIME', $clean);
        self::assertStringContainsString('IDAT', $clean);
        self::assertStringContainsString('pixel-data', $clean);
    }

    public function testStripsWebpMetadataChunks(): void
    {
        $vp8x = $this->webpChunk('VP8X', str_repeat("\x00", 10));
        $exif = $this->webpChunk('EXIF', 'secret exif');
        $xmp = $this->webpChunk('XMP ', 'secret xmp');
        $pixels = $this->webpChunk('VP8 ', 'pixel-data');
        $body = 'WEBP' . $vp8x . $exif . $xmp . $pixels;
        $webp = 'RIFF' . pack('V', strlen($body)) . $body;

        $clean = $this->sanitizer()->stripMetadata($webp);

        self::assertStringStartsWith('RIFF', $clean);
        self::assertStringNotContainsString('EXIF', $clean);
        self::assertStringNotContainsString('XMP ', $clean);
        self::assertStringContainsString('VP8 ', $clean);
        self::assertStringContainsString('pixel-data', $clean);
    }

    public function testSanitizesInlineBase64ImageResponse(): void
    {
        $png = "\x89PNG\r\n\x1A\n"
            . $this->pngChunk('IHDR', str_repeat("\x00", 13))
            . $this->pngChunk('tEXt', "Software\x00Secret")
            . $this->pngChunk('IDAT', 'pixels')
            . $this->pngChunk('IEND', '');

        $response = OpenAiResponse::json([
            'created' => 123,
            'data' => [
                ['b64_json' => base64_encode($png)],
                ['url' => 'https://example.test/image.png'],
            ],
        ]);

        $sanitized = $this->sanitizer()->sanitizeResponse(
            $response,
            'bailian',
            PlatformCapability::IMAGES,
            new NullLogger()
        );

        $decoded = json_decode((string) $sanitized->body, true, 512, JSON_THROW_ON_ERROR);
        $cleanPng = base64_decode($decoded['data'][0]['b64_json'], true);

        self::assertStringNotContainsString('Secret', (string) $cleanPng);
        self::assertSame('https://example.test/image.png', $decoded['data'][1]['url']);
        self::assertSame('1', $sanitized->headers['X-Grotto-Image-Sanitized']);
    }

    public function testLeavesUrlOnlyImagesUntouched(): void
    {
        $response = OpenAiResponse::json([
            'data' => [['url' => 'https://example.test/image.png']],
        ]);

        $sanitized = $this->sanitizer()->sanitizeResponse(
            $response,
            'bailian',
            PlatformCapability::IMAGES,
            new NullLogger()
        );

        self::assertSame($response, $sanitized);
    }

    public function testDisabledSanitizerReturnsSameResponse(): void
    {
        $config = $this->config(['GROTTO_SANITIZE_IMAGES' => 'false']);
        $sanitizer = new ImageSanitizer($config);
        $response = OpenAiResponse::json(['data' => [['b64_json' => base64_encode('junk')]]]);

        self::assertSame(
            $response,
            $sanitizer->sanitizeResponse($response, 'bailian', PlatformCapability::IMAGES, new NullLogger())
        );
    }

    private function sanitizer(): ImageSanitizer
    {
        return new ImageSanitizer($this->config());
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private function webpChunk(string $fourcc, string $data): string
    {
        return $fourcc . pack('V', strlen($data)) . $data . (strlen($data) % 2 ? "\x00" : '');
    }
}
