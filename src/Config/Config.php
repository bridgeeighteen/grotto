<?php

declare(strict_types=1);

namespace Grotto\Config;

use Dotenv\Dotenv;
use Grotto\Support\Env;
use RuntimeException;

/**
 * Immutable application configuration assembled from config files and the
 * environment. Secrets are decrypted on demand and are never included in the
 * metadata returned by the public info endpoint.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $grotto;

    /** @var array<string, array<string, mixed>> */
    private array $platformDefinitions;

    /** @var array<string, mixed> */
    private array $middleware;

    /** @var array<string, array<string, mixed>> */
    private array $platformCache = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly string $root,
    ) {
        $this->grotto = $this->loadConfig('grotto.php');
        $this->platformDefinitions = $this->loadConfig('platforms.php');
        $this->middleware = $this->loadConfig('middleware.php');

        $this->applyEnvironment();
    }

    public static function loadEnvironment(string $root): void
    {
        if (!is_file($root . '/.env')) {
            return;
        }

        Dotenv::createImmutable($root)->safeLoad();
    }

    public function root(): string
    {
        return $this->root;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->grotto;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function name(): string
    {
        return (string) $this->get('name', 'Grotto');
    }

    public function version(): string
    {
        return (string) $this->get('version', '0.0.0');
    }

    public function publicBaseUrl(): ?string
    {
        $url = Env::get('GROTTO_PUBLIC_BASE_URL');

        return is_string($url) && trim($url) !== '' ? rtrim(trim($url), '/') : null;
    }

    /**
     * IP addresses or CIDR ranges whose X-Forwarded-* headers are trusted.
     *
     * @return list<string>
     */
    public function trustedProxies(): array
    {
        return Env::list('GROTTO_TRUSTED_PROXIES', (array) $this->get('http.trusted_proxies', []));
    }

    /**
     * Describes where the upstream API key comes from. Keys are supplied by
     * the client on every proxy request and are never persisted by Grotto.
     *
     * @return array<string, mixed>
     */
    public function apiKeyPolicy(): array
    {
        return (array) $this->get('api_key', [
            'header' => 'Authorization',
            'scheme' => 'Bearer',
            'source' => 'request',
            'stored' => false,
        ]);
    }

    /**
     * @return list<string>
     */
    public function supportedLocales(): array
    {
        return array_values((array) $this->get('i18n.supported', ['en', 'zh-CN']));
    }

    /**
     * @return array<string, mixed>
     */
    public function rateLimit(): array
    {
        $config = $this->get('rate_limit', []);
        $config['enabled'] = Env::bool('GROTTO_RATE_LIMIT_ENABLED', (bool) ($config['enabled'] ?? true));
        $config['max_requests'] = max(1, Env::int('GROTTO_RATE_LIMIT_MAX', (int) ($config['max_requests'] ?? 60)));
        $config['window_seconds'] = max(1, Env::int('GROTTO_RATE_LIMIT_WINDOW', (int) ($config['window_seconds'] ?? 60)));
        $storage = (string) Env::get('GROTTO_RATE_LIMIT_STORAGE', $config['storage'] ?? 'storage/cache/rate_limit');
        $config['storage'] = $this->absolutePath($storage);

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function cors(): array
    {
        $config = $this->get('cors', []);
        $origins = Env::list('GROTTO_CORS_ORIGINS', (array) ($config['allowed_origins'] ?? ['*']));
        $credentials = Env::bool('GROTTO_CORS_CREDENTIALS', (bool) ($config['allow_credentials'] ?? false));

        if ($credentials && in_array('*', $origins, true)) {
            $credentials = false;
            $this->warnings[] = 'CORS credentials were disabled because GROTTO_CORS_ORIGINS contains *.';
        }

        $config['allowed_origins'] = $origins;
        $config['allow_credentials'] = $credentials;

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function logging(): array
    {
        $config = $this->get('logging', []);
        $config['level'] = (string) Env::get('GROTTO_LOG_LEVEL', $config['level'] ?? 'info');
        $config['path'] = $this->absolutePath((string) Env::get('GROTTO_LOG_PATH', $config['path'] ?? 'storage/logs/grotto.log'));

        return $config;
    }

    public function logPath(): string
    {
        return (string) ($this->logging()['path'] ?? $this->root . '/storage/logs/grotto.log');
    }

    /**
     * @return array<string, mixed>
     */
    public function http(): array
    {
        $config = $this->get('http', []);
        $config['connect_timeout'] = max(0.1, (float) Env::get('GROTTO_UPSTREAM_CONNECT_TIMEOUT', $config['connect_timeout'] ?? 10.0));
        $config['timeout'] = max(1.0, (float) Env::get('GROTTO_UPSTREAM_TIMEOUT', $config['timeout'] ?? 120.0));
        $config['verify'] = Env::bool('GROTTO_UPSTREAM_VERIFY_SSL', (bool) ($config['verify'] ?? true));
        $config['user_agent'] = 'Grotto/' . $this->version();

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function async(): array
    {
        $config = $this->get('async', []);
        $config['poll_interval_ms'] = max(10, Env::int('GROTTO_ASYNC_POLL_INTERVAL_MS', (int) ($config['poll_interval_ms'] ?? 1000)));
        $config['timeout_seconds'] = max(1, Env::int('GROTTO_ASYNC_TIMEOUT', (int) ($config['timeout_seconds'] ?? 300)));
        $config['max_attempts'] = max(1, Env::int('GROTTO_ASYNC_MAX_ATTEMPTS', (int) ($config['max_attempts'] ?? 600)));

        return $config;
    }

    public function systemPromptEnabled(): bool
    {
        return (bool) $this->get('system_prompt.enabled', true)
            && Env::bool('GROTTO_SYSTEM_PROMPT_ENABLED', true);
    }

    public function systemPrompt(): string
    {
        $prompt = Env::get('GROTTO_SYSTEM_PROMPT');
        $prompt = is_string($prompt) && trim($prompt) !== ''
            ? $prompt
            : (string) $this->get('default_system_prompt', '');

        return trim($prompt);
    }

    public function systemPromptExposed(): bool
    {
        return (bool) $this->get('system_prompt.expose', true)
            && Env::bool('GROTTO_EXPOSE_SYSTEM_PROMPT', true);
    }

    /**
     * @return list<string>
     */
    public function lowInformationSystemPrompts(): array
    {
        return array_values((array) $this->get('low_information_system_prompts', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function imageSanitizer(): array
    {
        $config = $this->get('image_sanitizer', []);
        $config['enabled'] = Env::bool('GROTTO_SANITIZE_IMAGES', (bool) ($config['enabled'] ?? true));
        $platforms = Env::list('GROTTO_SANITIZE_PLATFORMS', (array) ($config['platforms'] ?? ['*']));
        $config['platforms'] = $platforms;

        return $config;
    }

    public function uiEnabled(): bool
    {
        return Env::bool('GROTTO_UI_ENABLED', (bool) $this->get('ui.enabled', true));
    }

    /**
     * @return array<string, mixed>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return list<string>
     */
    public function platformIds(): array
    {
        return array_keys($this->platformDefinitions);
    }

    /**
     * @return list<string>
     */
    public function enabledPlatformIds(): array
    {
        $configured = Env::list('GROTTO_PLATFORMS');
        $ids = $configured === [] ? $this->platformIds() : $configured;

        return array_values(array_filter(
            $ids,
            fn (string $id): bool => isset($this->platformDefinitions[$id])
        ));
    }

    public function isPlatformEnabled(string $id): bool
    {
        return in_array($id, $this->enabledPlatformIds(), true);
    }

    /**
     * Metadata plus resolved non-secret configuration for a platform.
     *
     * @return array<string, mixed>
     */
    public function platform(string $id): array
    {
        if (isset($this->platformCache[$id])) {
            return $this->platformCache[$id];
        }

        if (!isset($this->platformDefinitions[$id])) {
            throw new RuntimeException(sprintf('Unknown platform "%s".', $id));
        }

        $definition = $this->platformDefinitions[$id];
        $baseUrl = Env::get((string) $definition['base_url_env'], $definition['default_base_url']);
        $baseUrl = is_string($baseUrl) && trim($baseUrl) !== ''
            ? rtrim(trim($baseUrl), '/')
            : (string) $definition['default_base_url'];

        $capabilities = array_values((array) $definition['capabilities']);

        if (isset($definition['optional_capabilities'])) {
            $audio = $definition['optional_capabilities'];
            if (Env::bool((string) $audio['audio_env'], false)) {
                $capabilities = array_merge($capabilities, (array) $audio['capabilities']);
            }
        }

        $resolved = $definition;
        $resolved['enabled'] = $this->isPlatformEnabled($id);
        $resolved['base_url'] = $baseUrl;
        $resolved['capabilities'] = array_values(array_unique($capabilities));

        return $this->platformCache[$id] = $resolved;
    }

    /**
     * Warnings are safe to show: they never contain secret values.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return array_values(array_unique($this->warnings));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(string $file): array
    {
        $path = $this->root . '/config/' . $file;

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Missing configuration file: %s', $path));
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new RuntimeException(sprintf('Configuration file must return an array: %s', $path));
        }

        return $config;
    }

    private function applyEnvironment(): void
    {
        $this->grotto['system_prompt']['enabled'] = $this->systemPromptEnabled();
        $this->grotto['system_prompt']['expose'] = $this->systemPromptExposed();
        $this->grotto['default_system_prompt'] = $this->systemPrompt();
        $this->grotto['rate_limit']['enabled'] = Env::bool(
            'GROTTO_RATE_LIMIT_ENABLED',
            (bool) ($this->grotto['rate_limit']['enabled'] ?? true)
        );

        // Resolve eagerly so configuration mistakes fail at startup, not on the
        // first proxied request.
        foreach ($this->enabledPlatformIds() as $id) {
            $this->platform($id);
        }
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return $this->root . '/' . ltrim($path, '/');
    }
}
