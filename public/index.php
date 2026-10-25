<?php

declare(strict_types=1);

use Grotto\App\ApplicationFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $app = ApplicationFactory::create();
    $app->run();
} catch (Throwable $exception) {
    error_log(sprintf(
        '[Grotto] Startup failure: %s in %s:%d',
        $exception->getMessage(),
        basename($exception->getFile()),
        $exception->getLine()
    ));

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode([
        'error' => [
            'message' => 'Grotto could not start. Check the server error log for the configuration error.',
            'type' => 'server_error',
            'code' => 'startup_failure',
            'param' => null,
        ],
    ], JSON_UNESCAPED_SLASHES);
}
