<?php

declare(strict_types=1);

namespace Grotto\Support;

use RuntimeException;

/**
 * A small fixed-window limiter backed by lock-protected files. It intentionally
 * has no external service dependency; deployments with multiple hosts should
 * swap this for a shared counter (Redis, database) via the same call site.
 */
final class FileRateLimiter
{
    public function __construct(
        private readonly string $directory,
        private readonly int $limit,
        private readonly int $windowSeconds,
    ) {
    }

    public function hit(string $identity): RateLimitResult
    {
        $this->ensureDirectory();

        $now = time();
        $window = intdiv($now, $this->windowSeconds);
        $resetAt = ($window + 1) * $this->windowSeconds;
        $file = sprintf('%s/%s.%d', $this->directory, hash('sha256', $identity), $window);

        $handle = fopen($file, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Could not open the rate-limit counter.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Could not lock the rate-limit counter.');
            }

            $contents = stream_get_contents($handle);
            $count = $contents === false ? 0 : (int) trim($contents);

            if ($count >= $this->limit) {
                return new RateLimitResult(
                    false,
                    $this->limit,
                    0,
                    $resetAt,
                    max(1, $resetAt - $now)
                );
            }

            $count++;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, (string) $count);
            fflush($handle);

            $this->maybeCleanup($window);

            return new RateLimitResult(
                true,
                $this->limit,
                max(0, $this->limit - $count),
                $resetAt,
                0
            );
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Could not create rate-limit directory: %s', $this->directory));
        }
    }

    private function maybeCleanup(int $currentWindow): void
    {
        if (random_int(1, 100) > 2) {
            return;
        }

        $files = glob($this->directory . '/*.*') ?: [];
        $cutoff = $currentWindow - 2;

        foreach ($files as $file) {
            $suffix = substr($file, (int) strrpos($file, '.') + 1);
            if (ctype_digit($suffix) && (int) $suffix < $cutoff) {
                @unlink($file);
            }
        }
    }
}
