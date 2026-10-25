<?php

declare(strict_types=1);

namespace Grotto\Tests;

use Grotto\App\ApplicationFactory;
use Grotto\Config\Config;
use Grotto\Support\Env;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Slim\App;

abstract class TestCase extends BaseTestCase
{
    protected string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__);
        Env::reset();
        Env::override($this->defaultEnvironment());
    }

    protected function tearDown(): void
    {
        Env::reset();
        parent::tearDown();
    }

    protected function config(array $overrides = []): Config
    {
        Env::override(array_merge($this->defaultEnvironment(), $overrides));

        return new Config($this->root);
    }

    /**
     * @param array<string, string> $overrides
     */
    protected function app(array $overrides = []): App
    {
        return ApplicationFactory::create(array_merge($this->defaultEnvironment(), $overrides));
    }

    /**
     * @return array<string, string>
     */
    protected function defaultEnvironment(): array
    {
        $tmp = '/tmp/opencode/grotto-tests';

        if (!is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        return [
            'GROTTO_RATE_LIMIT_ENABLED' => 'false',
            'GROTTO_LOG_PATH' => $tmp . '/test.log',
            'GROTTO_LOG_LEVEL' => 'debug',
            'GROTTO_PLATFORMS' => 'bailian,modelscope,volcengine',
            'GROTTO_UPSTREAM_VERIFY_SSL' => 'false',
            'GROTTO_ASYNC_POLL_INTERVAL_MS' => '10',
            'GROTTO_ASYNC_TIMEOUT' => '5',
            'GROTTO_ASYNC_MAX_ATTEMPTS' => '20',
            'GROTTO_SANITIZE_IMAGES' => 'true',
            'GROTTO_PUBLIC_BASE_URL' => 'https://grotto.test',
        ];
    }
}
