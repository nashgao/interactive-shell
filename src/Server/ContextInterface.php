<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server;

use Psr\Container\ContainerInterface;

/**
 * Framework context abstraction for server-side command handlers.
 *
 * This interface provides a uniform way for handlers to access framework
 * services like the DI container and configuration without coupling to
 * a specific framework implementation.
 */
interface ContextInterface
{
    /**
     * Get the PSR-11 container instance.
     */
    public function getContainer(): ContainerInterface;

    /**
     * Get all configuration as an array.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * Get a configuration value by dot-notation key.
     *
     * @param string $key The configuration key (e.g., 'database.default')
     * @param mixed $default Default value if key not found
     * @return mixed The configuration value
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if a configuration key exists.
     *
     * @param string $key The configuration key
     */
    public function has(string $key): bool;
}
