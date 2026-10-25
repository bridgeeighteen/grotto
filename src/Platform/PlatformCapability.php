<?php

declare(strict_types=1);

namespace Grotto\Platform;

final class PlatformCapability
{
    public const CHAT = 'chat';
    public const IMAGES = 'images';
    public const VIDEOS = 'videos';
    public const TTS = 'tts';
    public const STT = 'stt';
    public const MODELS = 'models';

    /** @var list<string> */
    public const ALL = [
        self::CHAT,
        self::IMAGES,
        self::VIDEOS,
        self::TTS,
        self::STT,
        self::MODELS,
    ];

    public static function fromPath(string $path): ?string
    {
        $path = trim($path, '/');

        if (str_starts_with($path, 'v1/')) {
            $path = substr($path, 3);
        }

        return match ($path) {
            'chat/completions' => self::CHAT,
            'images/generations' => self::IMAGES,
            'videos/generations' => self::VIDEOS,
            'audio/speech' => self::TTS,
            'audio/transcriptions' => self::STT,
            'models' => self::MODELS,
            default => null,
        };
    }

    public static function openAiPath(string $capability): ?string
    {
        return match ($capability) {
            self::CHAT => 'chat/completions',
            self::IMAGES => 'images/generations',
            self::VIDEOS => 'videos/generations',
            self::TTS => 'audio/speech',
            self::STT => 'audio/transcriptions',
            self::MODELS => 'models',
            default => null,
        };
    }

    public static function label(string $capability): string
    {
        return match ($capability) {
            self::CHAT => 'Chat',
            self::IMAGES => 'Images',
            self::VIDEOS => 'Video',
            self::TTS => 'Text to speech',
            self::STT => 'Speech to text',
            self::MODELS => 'Models',
            default => $capability,
        };
    }
}
