<?php

declare(strict_types=1);

use Grotto\Platform\Bailian\BailianAdapter;
use Grotto\Platform\ModelScope\ModelScopeAdapter;
use Grotto\Platform\Volcengine\VolcengineAdapter;
use Grotto\Platform\PlatformCapability;

return [
    'bailian' => [
        'id' => 'bailian',
        'label' => 'Alibaba Cloud Bailian',
        'label_zh' => '阿里云百炼',
        'short_label' => 'Bailian',
        'short_label_zh' => '百炼',
        'adapter' => BailianAdapter::class,
        'route' => '/bailian',
        'custom_route' => '/bailian/custom/{region}/{workspaceId}',
        'base_url_env' => 'BAILIAN_BASE_URL',
        'default_base_url' => 'https://dashscope.aliyuncs.com',
        'capabilities' => [
            PlatformCapability::CHAT,
            PlatformCapability::IMAGES,
            PlatformCapability::VIDEOS,
            PlatformCapability::TTS,
            PlatformCapability::STT,
            PlatformCapability::MODELS,
        ],
        'default_models' => [
            PlatformCapability::CHAT => 'qwen-plus',
            PlatformCapability::IMAGES => 'wanx2.1-t2i-turbo',
            PlatformCapability::VIDEOS => 'wan2.2-t2v-plus',
            PlatformCapability::TTS => 'cosyvoice-v2',
        ],
        'notes' => 'Custom workspace domains use {workspaceId}.{region}.maas.aliyuncs.com.',
    ],
    'modelscope' => [
        'id' => 'modelscope',
        'label' => 'ModelScope',
        'label_zh' => '魔搭社区',
        'short_label' => 'ModelScope',
        'short_label_zh' => '魔搭',
        'adapter' => ModelScopeAdapter::class,
        'route' => '/modelscope',
        'base_url_env' => 'MODELSCOPE_BASE_URL',
        'default_base_url' => 'https://api-inference.modelscope.cn/v1',
        'capabilities' => [
            PlatformCapability::CHAT,
            PlatformCapability::IMAGES,
            PlatformCapability::VIDEOS,
            PlatformCapability::MODELS,
        ],
        'optional_capabilities' => [
            'audio_env' => 'MODELSCOPE_AUDIO_ENABLED',
            'capabilities' => [PlatformCapability::TTS, PlatformCapability::STT],
        ],
        'default_models' => [
            PlatformCapability::CHAT => 'Qwen/Qwen3-235B-A22B-Instruct-2507',
            PlatformCapability::IMAGES => 'Qwen/Qwen-Image',
            PlatformCapability::VIDEOS => 'Wan-AI/Wan2.1-T2V-14B',
        ],
        'notes' => 'Image and video tasks use the ModelScope asynchronous task API and are polled by Grotto.',
    ],
    'volcengine' => [
        'id' => 'volcengine',
        'label' => 'Volcengine Ark',
        'label_zh' => '火山方舟',
        'short_label' => 'Ark',
        'short_label_zh' => '方舟',
        'adapter' => VolcengineAdapter::class,
        'route' => '/volcengine',
        'base_url_env' => 'VOLCENGINE_BASE_URL',
        'default_base_url' => 'https://ark.cn-beijing.volces.com/api/v3',
        'capabilities' => [
            PlatformCapability::CHAT,
            PlatformCapability::IMAGES,
            PlatformCapability::VIDEOS,
            PlatformCapability::TTS,
            PlatformCapability::STT,
            PlatformCapability::MODELS,
        ],
        'default_models' => [
            PlatformCapability::CHAT => 'doubao-seed-1-6-250615',
            PlatformCapability::IMAGES => 'doubao-seedream-3-0-t2i-250415',
            PlatformCapability::VIDEOS => 'doubao-seedance-1-0-pro-250528',
        ],
        'notes' => 'Video generation is submitted to the Ark content-generation task API and polled by Grotto.',
    ],
];
