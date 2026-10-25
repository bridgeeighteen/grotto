<?php

declare(strict_types=1);

use Grotto\Http\Middleware\CorsMiddleware;
use Grotto\Http\Middleware\PlatformKeyMiddleware;
use Grotto\Http\Middleware\RateLimitMiddleware;
use Grotto\Http\Middleware\RequestLogMiddleware;

/*
 * The list is ordered outermost first. ApplicationFactory adds the stack in
 * reverse because Slim executes middleware last-in, first-out.
 *
 * CORS sits outside the key extractor so browser preflights succeed without a
 * bearer token. Rate limiting sits after the extractor so it can key on a
 * one-way fingerprint of the caller's platform key.
 */
return [
    'global' => [
        RequestLogMiddleware::class,
        CorsMiddleware::class,
        PlatformKeyMiddleware::class,
        RateLimitMiddleware::class,
    ],
];
