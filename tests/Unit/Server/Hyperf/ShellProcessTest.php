<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Hyperf;

use NashGao\InteractiveShell\Server\Hyperf\ShellProcess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Unit tests for ShellProcess.
 *
 * Uses stub classes from tests/Stubs/Hyperf/ when Hyperf is not installed.
 * These tests focus on the configurable behavior that can be tested with mocks.
 */
#[CoversClass(ShellProcess::class)]
final class ShellProcessTest extends TestCase
{
    public function testIsEnableReturnsTrueByDefault(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $config->expects($this->once())
            ->method('get')
            ->with('interactive_shell.enabled', true)
            ->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);

        $process = new ShellProcess($container, $config);

        // The $server parameter is not used in isEnable, pass null
        $this->assertTrue($process->isEnable(null));
    }

    public function testIsEnableReturnsTrueWhenExplicitlyEnabled(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $config->expects($this->once())
            ->method('get')
            ->with('interactive_shell.enabled', true)
            ->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);

        $process = new ShellProcess($container, $config);

        $this->assertTrue($process->isEnable(null));
    }

    public function testIsEnableReturnsFalseWhenDisabled(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $config->expects($this->once())
            ->method('get')
            ->with('interactive_shell.enabled', true)
            ->willReturn(false);

        $container = $this->createMock(ContainerInterface::class);

        $process = new ShellProcess($container, $config);

        $this->assertFalse($process->isEnable(null));
    }

    public function testProcessNameIsInteractiveShell(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $container = $this->createMock(ContainerInterface::class);

        $process = new ShellProcess($container, $config);

        $this->assertSame('interactive-shell', $process->name);
    }
}
