<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Transport;

use NashGao\InteractiveShell\Transport\HttpTransport;
use NashGao\InteractiveShell\Transport\SwooleSocketTransport;
use NashGao\InteractiveShell\Transport\TransportFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransportFactory::class)]
final class TransportFactoryTest extends TestCase
{
    public function testSwooleAvailableReturnsTrue(): void
    {
        $result = TransportFactory::swooleAvailable();

        self::assertTrue($result);
    }

    public function testInSwooleCoroutineReturnsFalseOutsideCoroutine(): void
    {
        // Running in standard PHPUnit context (not inside Co\run)
        $result = TransportFactory::inSwooleCoroutine();

        self::assertFalse($result);
    }

    public function testGetContextInfoReturnsCorrectStructure(): void
    {
        $info = TransportFactory::getContextInfo();

        self::assertArrayHasKey('swoole_available', $info);
        self::assertArrayHasKey('in_coroutine', $info);
        self::assertArrayHasKey('coroutine_id', $info);
        self::assertArrayHasKey('recommended_transport', $info);

        self::assertIsBool($info['swoole_available']);
        self::assertIsBool($info['in_coroutine']);
        self::assertIsString($info['recommended_transport']);
    }

    public function testGetContextInfoCoroutineIdNullOutsideCoroutine(): void
    {
        $info = TransportFactory::getContextInfo();

        // Outside coroutine, coroutine_id should be null
        self::assertNull($info['coroutine_id']);
    }

    public function testGetContextInfoRecommendedTransportOutsideCoroutine(): void
    {
        $info = TransportFactory::getContextInfo();

        self::assertSame('swoole_socket', $info['recommended_transport']);
    }

    public function testUnixReturnsSwooleSocketTransport(): void
    {
        $transport = TransportFactory::unix('/tmp/test.sock', 5.0);

        self::assertInstanceOf(SwooleSocketTransport::class, $transport);
    }

    public function testUnixTransportEndpoint(): void
    {
        $transport = TransportFactory::unix('/tmp/test.sock');

        self::assertSame('swoole+unix:///tmp/test.sock', $transport->getEndpoint());
    }

    public function testPooledThrowsOutsideCoroutine(): void
    {
        // Pooled transport requires Swoole coroutine context
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pooled transport requires Swoole coroutine context');

        TransportFactory::pooled('/tmp/test.sock', 10, 5.0);
    }

    public function testSwooleReturnsSwooleSocketTransport(): void
    {
        $transport = TransportFactory::swoole('/tmp/test.sock', 5.0);

        self::assertInstanceOf(SwooleSocketTransport::class, $transport);
        self::assertSame('swoole+unix:///tmp/test.sock', $transport->getEndpoint());
    }

    public function testHttpReturnsHttpTransport(): void
    {
        $transport = TransportFactory::http('http://localhost:9501', 5.0);

        self::assertInstanceOf(HttpTransport::class, $transport);
        self::assertSame('http://localhost:9501', $transport->getEndpoint());
    }

    public function testHttpTransportWithDefaults(): void
    {
        $transport = TransportFactory::http('https://api.example.com');

        self::assertInstanceOf(HttpTransport::class, $transport);
        self::assertSame('https://api.example.com', $transport->getEndpoint());
    }
}
