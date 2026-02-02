<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server;

use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;
use NashGao\InteractiveShell\Server\SocketServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SocketServer.
 *
 * Note: Many SocketServer methods require Swoole coroutine runtime.
 * These tests focus on methods that can be tested without Swoole,
 * or verify behavior through state inspection.
 */
#[CoversClass(SocketServer::class)]
final class SocketServerTest extends TestCase
{
    private string $socketPath;
    private CommandRegistry $registry;
    private ContextInterface $context;

    protected function setUp(): void
    {
        $this->socketPath = sys_get_temp_dir() . '/test-shell-' . uniqid() . '.sock';
        $this->registry = new CommandRegistry();
        $this->context = $this->createMock(ContextInterface::class);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->socketPath)) {
            @unlink($this->socketPath);
        }
    }

    public function testIsRunningReturnsFalseInitially(): void
    {
        $server = new SocketServer(
            socketPath: $this->socketPath,
            registry: $this->registry,
            context: $this->context
        );

        $this->assertFalse($server->isRunning());
    }

    public function testGetEndpointReturnsSocketPath(): void
    {
        $server = new SocketServer(
            socketPath: $this->socketPath,
            registry: $this->registry,
            context: $this->context
        );

        $this->assertSame($this->socketPath, $server->getEndpoint());
    }

    public function testConstructorAcceptsCustomSocketPath(): void
    {
        $customPath = '/custom/path/shell.sock';

        $server = new SocketServer(
            socketPath: $customPath,
            registry: $this->registry,
            context: $this->context
        );

        $this->assertSame($customPath, $server->getEndpoint());
    }

    public function testConstructorAcceptsPermissions(): void
    {
        // Permissions are stored internally and used when starting
        // We can only verify construction doesn't throw
        $server = new SocketServer(
            socketPath: $this->socketPath,
            registry: $this->registry,
            context: $this->context,
            socketPermissions: 0644
        );

        $this->assertInstanceOf(SocketServer::class, $server);
    }

    public function testStopIsIdempotentWhenNotRunning(): void
    {
        $server = new SocketServer(
            socketPath: $this->socketPath,
            registry: $this->registry,
            context: $this->context
        );

        // Calling stop when not running should not throw
        $server->stop();
        $server->stop();

        $this->assertFalse($server->isRunning());
    }
}
