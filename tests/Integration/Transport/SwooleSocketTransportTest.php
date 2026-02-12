<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration\Transport;

use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Transport\SwooleSocketTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SwooleSocketTransport::class)]
final class SwooleSocketTransportTest extends TestCase
{
    public function testConstructorSetsSocketPath(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        self::assertSame('swoole+unix:///tmp/test.sock', $transport->getEndpoint());
    }

    public function testConstructorWithTimeout(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock', 5.0);

        $info = $transport->getInfo();

        self::assertSame(5.0, $info['timeout']);
    }

    public function testIsConnectedReturnsFalseInitially(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        self::assertFalse($transport->isConnected());
    }

    public function testSupportsStreamingReturnsTrue(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        self::assertTrue($transport->supportsStreaming());
    }

    public function testIsStreamingReturnsFalseInitially(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        self::assertFalse($transport->isStreaming());
    }

    public function testGetInfoReturnsCorrectStructure(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock', 2.5);

        $info = $transport->getInfo();

        self::assertSame('swoole_socket', $info['type']);
        self::assertSame('/tmp/test.sock', $info['path']);
        self::assertFalse($info['connected']);
        self::assertFalse($info['streaming']);
        self::assertSame(2.5, $info['timeout']);
    }

    public function testGetEndpointReturnsCorrectFormat(): void
    {
        $transport = new SwooleSocketTransport('/var/run/app.sock');

        self::assertSame('swoole+unix:///var/run/app.sock', $transport->getEndpoint());
    }

    public function testOnMessageRegistersCallback(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');
        $called = false;
        $receivedMessage = null;

        $transport->onMessage(function (Message $message) use (&$called, &$receivedMessage): void {
            $called = true;
            $receivedMessage = $message;
        });

        $message = Message::system('test message');
        $transport->dispatchMessage($message);

        self::assertTrue($called);
        self::assertSame($message, $receivedMessage);
    }

    public function testDispatchMessageWithNoCallback(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        // Should not throw when no callback is registered
        $message = Message::system('test');
        $transport->dispatchMessage($message);

        // Nothing to assert - just verify no exception
        self::assertTrue(true);
    }

    public function testDisconnectDoesNotThrowWhenNotConnected(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        // Should not throw
        $transport->disconnect();

        self::assertFalse($transport->isConnected());
    }

    public function testStopStreamingDoesNotThrowWhenNotConnected(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        // Should not throw when not connected
        $transport->stopStreaming();

        self::assertFalse($transport->isStreaming());
    }

    public function testSendReturnsFailureWhenNotConnected(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

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
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        self::assertFalse($transport->ping());
    }

    public function testReceiveReturnsNullWhenNotConnected(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        $message = $transport->receive(0);

        self::assertNull($message);
    }

    public function testSendAsyncThrowsWhenNotConnected(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

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
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not connected');

        $transport->startStreaming();
    }

    public function testConnectFailsForNonexistentSocket(): void
    {
        $transport = new SwooleSocketTransport('/nonexistent/path/socket.sock');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to connect');

        $transport->connect();
    }

    public function testMultipleDisconnectsDoNotThrow(): void
    {
        $transport = new SwooleSocketTransport('/tmp/test.sock');

        // Multiple disconnects should be safe
        $transport->disconnect();
        $transport->disconnect();
        $transport->disconnect();

        self::assertFalse($transport->isConnected());
    }
}
