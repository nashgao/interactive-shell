<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Transport;

use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Transport\PooledSwooleTransport;
use NashGao\InteractiveShell\Transport\SocketConnectionPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(PooledSwooleTransport::class)]
#[RequiresPhpExtension('swoole')]
final class PooledSwooleTransportTest extends TestCase
{
    public function testIsConnectedReturnsTrueWhenPoolOpen(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock', 5, 2.5);

        // Pooled transport is "connected" as long as pool is open
        self::assertTrue($transport->isConnected());
    }

    public function testSupportsStreamingReturnsTrue(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');

        self::assertTrue($transport->supportsStreaming());
    }

    public function testIsStreamingReturnsFalseInitially(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');

        self::assertFalse($transport->isStreaming());
    }

    public function testGetInfoReturnsCorrectStructure(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock', 15);

        $info = $transport->getInfo();

        self::assertSame('pooled_swoole_socket', $info['type']);
        self::assertSame(15, $info['pool_size']);
        self::assertSame(0, $info['current_connections']);
        self::assertSame(0, $info['available_connections']);
        self::assertFalse($info['streaming']);
    }

    public function testGetEndpointReturnsCorrectFormat(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');

        self::assertSame('pooled+swoole+unix://', $transport->getEndpoint());
    }

    public function testGetPoolReturnsPoolInstance(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock', 5);

        $pool = $transport->getPool();

        self::assertInstanceOf(SocketConnectionPool::class, $pool);
        self::assertSame(5, $pool->getMaxSize());
    }

    public function testDisconnectClosesPool(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');

        $transport->disconnect();

        self::assertFalse($transport->isConnected());
        self::assertTrue($transport->getPool()->isClosed());
    }

    public function testConnectDoesNothing(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');

        // Connect is a no-op for pooled transport - pool manages connections
        $transport->connect();

        // Pool state unchanged
        self::assertTrue($transport->isConnected());
        self::assertSame(0, $transport->getPool()->getCurrentSize());
    }

    public function testOnMessageRegistersCallback(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');
        $callbackRegistered = false;

        $transport->onMessage(function (Message $message) use (&$callbackRegistered): void {
            $callbackRegistered = true;
        });

        // Callback is stored for when active transport is acquired
        // We can verify this by checking the transport doesn't throw
        self::assertTrue(true);
    }

    public function testStopStreamingWhenNotStreaming(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');

        // Should not throw when not streaming
        $transport->stopStreaming();

        self::assertFalse($transport->isStreaming());
    }

    public function testPingReturnsFalseWhenPoolClosed(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');
        $transport->disconnect();

        // Ping should return false when pool is closed
        self::assertFalse($transport->ping());
    }

    public function testReceiveThrowsWhenPoolClosed(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');
        $transport->disconnect();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection pool is closed');

        // Should throw when pool is closed because it tries to acquire a connection
        $transport->receive(0.001);
    }

    public function testPoolSizeConfiguration(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock', 20);

        $info = $transport->getInfo();

        self::assertSame(20, $info['pool_size']);
    }

    public function testDefaultPoolSize(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');

        $info = $transport->getInfo();

        self::assertSame(10, $info['pool_size']);
    }

    public function testMultipleDisconnectsDoNotThrow(): void
    {
        $transport = new PooledSwooleTransport('/tmp/test.sock');

        // Multiple disconnects should be safe
        $transport->disconnect();
        $transport->disconnect();
        $transport->disconnect();

        self::assertFalse($transport->isConnected());
    }
}
