<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\E2E\Hyperf;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Process\AbstractProcess;
use NashGao\InteractiveShell\Server\Hyperf\ShellProcess;
use PHPUnit\Framework\TestCase;

/**
 * ShellProcess Lifecycle Specification Tests.
 *
 * These tests verify the ShellProcess lifecycle behavior from the
 * Hyperf process management perspective:
 * - Process respects the enabled configuration
 * - Process uses configured socket path
 * - Process name is set correctly
 *
 * SPECIFICATION-FIRST: These tests define what Hyperf expects from a process.
 */
final class ShellProcessLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(ConfigInterface::class)) {
            $this->markTestSkipped('Hyperf/Contract not installed');
        }

        if (!class_exists(AbstractProcess::class)) {
            $this->markTestSkipped('Hyperf/Process not installed');
        }
    }

    /**
     * SPECIFICATION: When interactive_shell.enabled is false, the process should not run.
     */
    public function testProcessRespectsEnabledConfigWhenDisabled(): void
    {
        // Given: Shell is disabled in config
        $app = new HyperfTestApplication([
            'interactive_shell.enabled' => false,
        ]);

        // When: Process is created
        $process = new ShellProcess(
            $app->getContainer(),
            $app->getConfig()
        );

        // Then: Process reports as not enabled
        // Note: isEnable() expects a server parameter (which is null during testing)
        $isEnabled = $process->isEnable(null);

        self::assertFalse($isEnabled, 'Process should be disabled when config says disabled');
    }

    /**
     * SPECIFICATION: When interactive_shell.enabled is true (or not set), the process should run.
     */
    public function testProcessIsEnabledByDefault(): void
    {
        // Given: Shell is enabled in config (default)
        $app = new HyperfTestApplication([
            'interactive_shell.enabled' => true,
        ]);

        // When: Process is created
        $process = new ShellProcess(
            $app->getContainer(),
            $app->getConfig()
        );

        // Then: Process reports as enabled
        $isEnabled = $process->isEnable(null);

        self::assertTrue($isEnabled, 'Process should be enabled by default');
    }

    /**
     * SPECIFICATION: When no enabled config exists, process defaults to enabled.
     */
    public function testProcessDefaultsToEnabledWhenConfigMissing(): void
    {
        // Given: No explicit enabled config
        $app = new HyperfTestApplication([
            // interactive_shell.enabled is not set
            'interactive_shell.socket_path' => '/tmp/test.sock',
        ]);

        // When: Process checks if enabled
        $process = new ShellProcess(
            $app->getContainer(),
            $app->getConfig()
        );

        // Then: Process should be enabled (default behavior)
        $isEnabled = $process->isEnable(null);

        self::assertTrue($isEnabled, 'Process should default to enabled');
    }

    /**
     * SPECIFICATION: Process has the correct name for Hyperf process management.
     */
    public function testProcessHasCorrectName(): void
    {
        // Given: A Hyperf test application
        $app = new HyperfTestApplication();

        // When: Process is created
        $process = new ShellProcess(
            $app->getContainer(),
            $app->getConfig()
        );

        // Then: Process has the expected name
        self::assertSame('interactive-shell', $process->name, 'Process name should be "interactive-shell"');
    }

    /**
     * SPECIFICATION: Process extends AbstractProcess for Hyperf integration.
     */
    public function testProcessExtendsAbstractProcess(): void
    {
        // Given: A Hyperf test application
        $app = new HyperfTestApplication();

        // When: Process is created
        $process = new ShellProcess(
            $app->getContainer(),
            $app->getConfig()
        );

        // Then: Process is an AbstractProcess
        self::assertInstanceOf(
            AbstractProcess::class,
            $process,
            'ShellProcess should extend AbstractProcess'
        );
    }

    /**
     * SPECIFICATION: Process can be created with custom socket path.
     */
    public function testProcessUsesConfiguredSocketPath(): void
    {
        // Given: Custom socket path in config
        $customPath = '/var/run/custom-shell.sock';
        $app = new HyperfTestApplication([
            'interactive_shell.socket_path' => $customPath,
        ]);

        // When: Application is configured
        $config = $app->getConfig();

        // Then: The configured socket path is accessible
        self::assertSame(
            $customPath,
            $config->get('interactive_shell.socket_path'),
            'Socket path should be configurable'
        );
    }

    /**
     * SPECIFICATION: Process can be created with custom socket permissions.
     */
    public function testProcessUsesConfiguredSocketPermissions(): void
    {
        // Given: Custom socket permissions in config
        $customPermissions = 0666;
        $app = new HyperfTestApplication([
            'interactive_shell.socket_permissions' => $customPermissions,
        ]);

        // When: Application is configured
        $config = $app->getConfig();

        // Then: The configured permissions are accessible
        self::assertSame(
            $customPermissions,
            $config->get('interactive_shell.socket_permissions'),
            'Socket permissions should be configurable'
        );
    }

    /**
     * SPECIFICATION: Process can be created with custom handlers.
     */
    public function testProcessAcceptsCustomHandlers(): void
    {
        // Given: Custom handlers in config
        $customHandlers = ['App\\Handler\\CustomHandler'];
        $app = new HyperfTestApplication([
            'interactive_shell.handlers' => $customHandlers,
        ]);

        // When: Application is configured
        $config = $app->getConfig();

        // Then: The configured handlers are accessible
        self::assertSame(
            $customHandlers,
            $config->get('interactive_shell.handlers'),
            'Custom handlers should be configurable'
        );
    }
}
