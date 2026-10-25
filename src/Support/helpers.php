<?php

declare(strict_types=1);

use Grotto\Support\Env;

if (!function_exists('env')) {
    /**
     * Read an environment value without leaking it into configuration defaults.
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('env_bool')) {
    function env_bool(string $key, bool $default = false): bool
    {
        return Env::bool($key, $default);
    }
}

if (!function_exists('env_int')) {
    function env_int(string $key, int $default = 0): int
    {
        return Env::int($key, $default);
    }
}

if (!function_exists('env_list')) {
    /**
     * @return list<string>
     */
    function env_list(string $key, array $default = []): array
    {
        return Env::list($key, $default);
    }
}
