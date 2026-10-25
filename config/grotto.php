<?php

declare(strict_types=1);

return [
    'name' => 'Grotto',
    'version' => '1.0.0',
    'tagline' => 'One OpenAI surface for three model clouds.',
    'default_system_prompt' => 'You are a helpful assistant. Be direct, accurate, and concise. If you do not know something, say so instead of guessing.',
    'low_information_system_prompts' => [
        'you are a helpful assistant',
        'you are a helpful ai assistant',
        'you are an ai assistant',
        'you are a helpful assistant that answers questions',
        'you are chatgpt, a large language model trained by openai',
        'you are a large language model',
    ],
    'system_prompt' => [
        'enabled' => true,
        'expose' => true,
    ],
    'api_key' => [
        'header' => 'Authorization',
        'scheme' => 'Bearer',
        'source' => 'request',
        'stored' => false,
    ],
    'i18n' => [
        'supported' => ['en', 'zh-CN'],
    ],
    'rate_limit' => [
        'enabled' => true,
        'max_requests' => 60,
        'window_seconds' => 60,
        'storage' => 'storage/cache/rate_limit',
    ],
    'cors' => [
        'allowed_origins' => ['*'],
        'allowed_headers' => [
            'Authorization',
            'Content-Type',
            'Accept',
            'X-Requested-With',
        ],
        'expose_headers' => [
            'X-Grotto-Request-Id',
            'X-Grotto-Platform',
            'X-Grotto-Upstream-Status',
            'X-RateLimit-Limit',
            'X-RateLimit-Remaining',
            'X-RateLimit-Reset',
        ],
        'max_age' => 86400,
        'allow_credentials' => false,
    ],
    'logging' => [
        'level' => 'info',
        'path' => 'storage/logs/grotto.log',
        'channel' => 'grotto',
    ],
    'http' => [
        'connect_timeout' => 10.0,
        'timeout' => 120.0,
        'verify' => true,
        'user_agent' => 'Grotto/1.0',
    ],
    'async' => [
        'poll_interval_ms' => 1000,
        'timeout_seconds' => 300,
        'max_attempts' => 600,
    ],
    'image_sanitizer' => [
        'enabled' => true,
        'platforms' => ['*'],
        'capabilities' => ['images'],
        'formats' => ['jpeg', 'png', 'webp'],
    ],
    'ui' => [
        'enabled' => true,
    ],
];
