<?php

declare(strict_types=1);

// Router for the PHP built-in development server. The frontend is served by
// UiController, so only files physically present under public/ are static.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $file = __DIR__ . (is_string($path) ? $path : '/');

    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';
