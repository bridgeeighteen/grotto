<?php

declare(strict_types=1);

namespace Grotto\Support;

use Grotto\Config\Config;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class Logger
{
    public static function create(Config $config): LoggerInterface
    {
        $settings = $config->logging();
        $path = (string) $settings['path'];
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create log directory: %s', $directory));
        }

        try {
            $level = Level::fromName(strtolower((string) $settings['level']));
        } catch (\ValueError) {
            $level = Level::Info;
        }

        $handler = new StreamHandler($path, $level);
        $handler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name% %message% %context% %extra%\n",
            'Y-m-d\TH:i:sP',
            true,
            true
        ));

        $logger = new Monolog((string) ($settings['channel'] ?? 'grotto'));
        $logger->pushHandler($handler);

        return $logger;
    }
}
