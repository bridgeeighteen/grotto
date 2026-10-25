<?php

declare(strict_types=1);

namespace Grotto\Platform;

use Grotto\Config\Config;
use Grotto\Core\Exception\ApiException;
use Psr\Container\ContainerInterface;

final class PlatformRegistry
{
    /** @var array<string, PlatformAdapterInterface> */
    private array $adapters = [];

    public function __construct(
        private readonly Config $config,
        private readonly ContainerInterface $container,
    ) {
    }

    public function adapter(string $id): PlatformAdapterInterface
    {
        if (isset($this->adapters[$id])) {
            return $this->adapters[$id];
        }

        if (!$this->config->isPlatformEnabled($id)) {
            throw ApiException::notFound(
                sprintf('Platform "%s" is not enabled on this Grotto instance.', $id),
                'platform_not_enabled'
            );
        }

        $platform = $this->config->platform($id);
        $class = (string) $platform['adapter'];

        if (!class_exists($class)) {
            throw ApiException::serviceUnavailable(
                sprintf('The adapter for "%s" is unavailable.', $id),
                'adapter_unavailable'
            );
        }

        $adapter = $this->container->get($class);

        if (!$adapter instanceof PlatformAdapterInterface) {
            throw ApiException::serviceUnavailable(
                sprintf('The adapter for "%s" is invalid.', $id),
                'adapter_invalid'
            );
        }

        return $this->adapters[$id] = $adapter;
    }

    /**
     * @return array<string, mixed>
     */
    public function platform(string $id): array
    {
        return $this->config->platform($id);
    }
}
