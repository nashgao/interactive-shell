<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Server;

use NashGao\InteractiveShell\Server\ContextInterface;
use Psr\Container\ContainerInterface;

/**
 * Simple test context with in-memory configuration.
 *
 * This is a minimal ContextInterface implementation for testing purposes.
 * It provides basic configuration storage without requiring a full
 * dependency injection container.
 *
 * Example usage:
 * ```php
 * // Basic usage with config values
 * $context = new TestContext(['app.name' => 'test-shell', 'debug' => true]);
 * $context->get('app.name');  // Returns 'test-shell'
 * $context->get('missing', 'default');  // Returns 'default'
 *
 * // With custom container
 * $container = new MyContainer();
 * $context = new TestContext(['key' => 'value'], $container);
 * $context->getContainer();  // Returns MyContainer instance
 *
 * // Use with TestServer
 * $server = new TestServer($context);
 * $server->register(new EchoHandler());
 * ```
 */
final class TestContext implements ContextInterface
{
    /**
     * @param array<string, mixed> $config Configuration key-value pairs
     * @param ContainerInterface|null $container Optional DI container
     */
    public function __construct(
        private readonly array $config = [],
        private readonly ?ContainerInterface $container = null,
    ) {}

    public function getContainer(): ContainerInterface
    {
        if ($this->container !== null) {
            return $this->container;
        }

        // Return a null-object container that throws on access
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \RuntimeException("No service registered for: {$id}");
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }
}
