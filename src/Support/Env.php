<?php

declare(strict_types=1);

namespace Grotto\Support;

/**
 * A tiny environment repository used by config files and tests.
 *
 * Dotenv writes into $_ENV/$_SERVER rather than the process environment so
 * that secrets are not exposed to child processes by default.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $overrides = [];

    /**
     * @param array<string, string|null> $values
     */
    public static function override(array $values): void
    {
        self::$overrides = [];

        foreach ($values as $key => $value) {
            if ($value !== null) {
                self::$overrides[$key] = $value;
            }
        }
    }

    public static function reset(): void
    {
        self::$overrides = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$overrides)) {
            return self::$overrides[$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        if ($value === null || !is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    public static function list(string $key, array $default = []): array
    {
        $value = self::get($key);

        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        $items = array_map('trim', explode(',', $value));

        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    }
}
