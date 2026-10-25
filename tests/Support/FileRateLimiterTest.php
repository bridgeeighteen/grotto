<?php

declare(strict_types=1);

namespace Grotto\Tests\Support;

use Grotto\Support\FileRateLimiter;
use Grotto\Tests\TestCase;

final class FileRateLimiterTest extends TestCase
{
    public function testLimitsWithinAWindow(): void
    {
        $dir = '/tmp/opencode/grotto-tests/limiter-' . bin2hex(random_bytes(4));
        $limiter = new FileRateLimiter($dir, 2, 60);

        $first = $limiter->hit('client-a');
        $second = $limiter->hit('client-a');
        $third = $limiter->hit('client-a');
        $other = $limiter->hit('client-b');

        self::assertTrue($first->allowed);
        self::assertSame(1, $first->remaining);
        self::assertTrue($second->allowed);
        self::assertSame(0, $second->remaining);
        self::assertFalse($third->allowed);
        self::assertGreaterThanOrEqual(1, $third->retryAfter);
        self::assertTrue($other->allowed);
    }
}
