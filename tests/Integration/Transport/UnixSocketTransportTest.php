<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration\Transport;

use NashGao\InteractiveShell\Transport\UnixSocketTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnixSocketTransport::class)]
final class UnixSocketTransportTest extends TestCase
{
    public function testConstructorSetsSocketPath(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        self::assertSame('unix:///tmp/test.sock', $transport->getEndpoint());
    }

    public function testIsConnectedReturnsFalseInitially(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        self::assertFalse($transport->isConnected());
    }

    public function testSupportsStreamingReturnsTrue(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        self::assertTrue($transport->supportsStreaming());
    }

    public function testIsStreamingReturnsFalseInitially(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        self::assertFalse($transport->isStreaming());
    }

    public function testGetInfoReturnsCorrectStructure(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        $info = $transport->getInfo();

        self::assertSame('unix_socket', $info['type']);
        self::assertSame('/tmp/test.sock', $info['path']);
        self::assertFalse($info['connected']);
        self::assertFalse($info['streaming']);
    }

    public function testConnectFailsForNonexistentSocket(): void
    {
        $transport = new UnixSocketTransport('/nonexistent/path/socket.sock');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to connect');

        $transport->connect();
    }

    public function testSendReturnsFailureWhenNotConnected(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        $command = new \NashGao\InteractiveShell\Parser\ParsedCommand(
            command: 'test',
            arguments: [],
            options: [],
            raw: 'test',
            hasVerticalTerminator: false
        );

        $result = $transport->send($command);

        self::assertFalse($result->success);
        self::assertSame('Not connected', $result->error);
    }

    public function testPingReturnsFalseWhenNotConnected(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        self::assertFalse($transport->ping());
    }

    public function testReceiveReturnsNullWhenNotConnected(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        $message = $transport->receive(0);

        self::assertNull($message);
    }

    public function testSendAsyncThrowsWhenNotConnected(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        $command = new \NashGao\InteractiveShell\Parser\ParsedCommand(
            command: 'test',
            arguments: [],
            options: [],
            raw: 'test',
            hasVerticalTerminator: false
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not connected');

        $transport->sendAsync($command);
    }

    public function testStartStreamingThrowsWhenNotConnected(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not connected');

        $transport->startStreaming();
    }

    public function testStopStreamingDoesNotThrowWhenNotConnected(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        // Should not throw
        $transport->stopStreaming();

        self::assertFalse($transport->isStreaming());
    }

    public function testDisconnectDoesNotThrowWhenNotConnected(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        // Should not throw
        $transport->disconnect();

        self::assertFalse($transport->isConnected());
    }

    public function testOnMessageRegistersCallback(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');
        $called = false;

        $transport->onMessage(function ($message) use (&$called) {
            $called = true;
        });

        // dispatchMessage will trigger the callback
        $message = \NashGao\InteractiveShell\Message\Message::system('test');
        $transport->dispatchMessage($message);

        self::assertTrue($called);
    }

    public function testDispatchMessageWithNoCallback(): void
    {
        $transport = new UnixSocketTransport('/tmp/test.sock');

        // Should not throw
        $message = \NashGao\InteractiveShell\Message\Message::system('test');
        $transport->dispatchMessage($message);

        // Nothing to assert - just verify no exception
        self::assertTrue(true);
    }
}
