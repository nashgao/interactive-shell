<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\E2E\Hyperf;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * Minimal Hyperf-like application for E2E testing.
 *
 * This helper provides a real ContainerInterface and ConfigInterface
 * without requiring the full Hyperf framework bootstrap. It's designed
 * to test the ShellProcess integration in isolation.
 *
 * Example usage:
 * ```php
 * $app = new HyperfTestApplication([
 *     'interactive_shell.enabled' => true,
 *     'interactive_shell.socket_path' => '/tmp/test.sock',
 * ]);
 *
 * $process = new ShellProcess($app->getContainer(), $app->getConfig());
 * // Test process behavior...
 * ```
 *
 * @see ShellProcess For the process being tested
 */
final class HyperfTestApplication
{
    private TestContainer $container;
    private TestConfig $config;

    /**
     * @param array<string, mixed> $configValues Configuration values to use
     */
    public function __construct(array $configValues = [])
    {
        // Apply defaults for interactive shell
        $defaults = [
            'interactive_shell.enabled' => true,
            'interactive_shell.socket_path' => sys_get_temp_dir() . '/hyperf-shell-test-' . uniqid() . '.sock',
            'interactive_shell.socket_permissions' => 0660,
            'interactive_shell.handlers' => [],
            'app_name' => 'test-app',
        ];

        $mergedConfig = array_merge($defaults, $configValues);

        $this->config = new TestConfig($mergedConfig);
        $this->container = new TestContainer();
        $this->container->set(ConfigInterface::class, $this->config);

        // Register a mock console application for CommandListHandler
        $consoleApp = new Application('test-app', '1.0.0');
        $consoleApp->add(new class extends Command {
            protected function configure(): void
            {
                $this->setName('ping');
                $this->setDescription('Test ping command');
            }
        });
        $consoleApp->add(new class extends Command {
            protected function configure(): void
            {
                $this->setName('config');
                $this->setDescription('Show configuration');
            }
        });
        $consoleApp->add(new class extends Command {
            protected function configure(): void
            {
                $this->setName('help');
                $this->setDescription('Show help');
            }
        });
        $this->container->set(Application::class, $consoleApp);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    /**
     * Get the configured socket path.
     */
    public function getSocketPath(): string
    {
        return $this->config->get('interactive_shell.socket_path', '');
    }

    /**
     * Check if the required Hyperf classes are available.
     */
    public static function isHyperfAvailable(): bool
    {
        return interface_exists(ConfigInterface::class)
            && interface_exists(ContainerInterface::class);
    }
}

/**
 * Simple test container implementation.
 */
final class TestContainer implements ContainerInterface
{
    /** @var array<string, object> */
    private array $services = [];

    public function get(string $id): object
    {
        if (!isset($this->services[$id])) {
            throw new \RuntimeException("Service not found: {$id}");
        }
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function set(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }
}

/**
 * Simple test config implementation.
 */
final class TestConfig implements ConfigInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config = [],
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        // Handle dot notation
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                // Also check for flat key
                if (array_key_exists($key, $this->config)) {
                    return $this->config[$key];
                }
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        // Handle dot notation
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                // Also check for flat key
                return array_key_exists($key, $this->config);
            }
            $value = $value[$k];
        }

        return true;
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }
}
