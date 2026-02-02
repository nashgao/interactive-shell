<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Transport;

use NashGao\InteractiveShell\Transport\SocketConnectionPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(SocketConnectionPool::class)]
#[RequiresPhpExtension('swoole')]
final class SocketConnectionPoolTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $pool = new SocketConnectionPool('/tmp/test.sock');

        self::assertSame(10, $pool->getMaxSize());
        self::assertSame(0, $pool->getCurrentSize());
        self::assertSame(0, $pool->getAvailableCount());
        self::assertFalse($pool->isClosed());
    }

    public function testConstructorWithCustomPoolSize(): void
    {
        $pool = new SocketConnectionPool('/tmp/test.sock', 5);

        self::assertSame(5, $pool->getMaxSize());
    }

    public function testGetMaxSizeReturnsConfiguredValue(): void
    {
        $pool = new SocketConnectionPool('/tmp/test.sock', 20);

        self::assertSame(20, $pool->getMaxSize());
    }

    public function testGetCurrentSizeReturnsZeroInitially(): void
    {
        $pool = new SocketConnectionPool('/tmp/test.sock');

        self::assertSame(0, $pool->getCurrentSize());
    }

    public function testGetAvailableCountReturnsZeroInitially(): void
    {
        $pool = new SocketConnectionPool('/tmp/test.sock');

        self::assertSame(0, $pool->getAvailableCount());
    }

    public function testIsClosedReturnsFalseInitially(): void
    {
        $pool = new SocketConnectionPool('/tmp/test.sock');

        self::assertFalse($pool->isClosed());
    }

    public function testCloseMarksPoolAsClosed(): void
    {
        $pool = new SocketConnectionPool('/tmp/test.sock');

        $pool->close();

        self::assertTrue($pool->isClosed());
    }

    public function testGetThrowsWhenPoolClosed(): void
    {
        $pool = new SocketConnectionPool('/tmp/test.sock');
        $pool->close();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection pool is closed');

        $pool->get();
    }

    public function testMultipleClosesDoNotThrow(): void
    {
        $pool = new SocketConnectionPool('/tmp/test.sock');

        // Multiple closes should be safe
        $pool->close();
        $pool->close();
        $pool->close();

        self::assertTrue($pool->isClosed());
    }

    public function testPoolSizeConfiguration(): void
    {
        $pool1 = new SocketConnectionPool('/tmp/test.sock', 5);
        $pool2 = new SocketConnectionPool('/tmp/test.sock', 100);

        self::assertSame(5, $pool1->getMaxSize());
        self::assertSame(100, $pool2->getMaxSize());
    }
}
