<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Hyperf;

use Hyperf\Contract\ConfigInterface;
use NashGao\InteractiveShell\Server\ContextInterface;
use Psr\Container\ContainerInterface;

/**
 * Hyperf-specific context implementation.
 *
 * Provides access to Hyperf's container and configuration system.
 */
final class HyperfContext implements ContextInterface
{
    private readonly ConfigInterface $config;

    public function __construct(
        private readonly ContainerInterface $container
    ) {
        $this->config = $container->get(ConfigInterface::class);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getConfig(): array
    {
        // Get all config keys and build array
        // Hyperf's config interface doesn't expose all keys directly,
        // so we work with known top-level keys
        $result = [];
        $topLevelKeys = $this->getTopLevelConfigKeys();

        foreach ($topLevelKeys as $key) {
            $value = $this->config->get($key);
            if ($value !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    public function has(string $key): bool
    {
        return $this->config->has($key);
    }

    /**
     * Get commonly used top-level configuration keys.
     *
     * @return array<string>
     */
    private function getTopLevelConfigKeys(): array
    {
        return [
            'app_name',
            'app_env',
            'scan_cacheable',
            'databases',
            'redis',
            'logger',
            'cache',
            'server',
            'middlewares',
            'exceptions',
            'listeners',
            'processes',
            'annotations',
            'aspects',
            'commands',
            'interactive_shell',
        ];
    }
}
