<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration;

use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\StreamingShell;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\EchoHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\StreamHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestServer;
use NashGao\InteractiveShell\Tests\Fixtures\Transport\InMemoryStreamingTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Integration tests for StreamingShell using InMemoryStreamingTransport.
 *
 * These tests verify the streaming functionality works correctly:
 * - Message queueing and delivery
 * - Streaming mode activation/deactivation
 * - Callback-based message handling
 * - Command execution in streaming context
 */
#[CoversClass(StreamingShell::class)]
#[CoversClass(InMemoryStreamingTransport::class)]
final class StreamingShellFlowTest extends TestCase
{
    private TestServer $server;
    private InMemoryStreamingTransport $transport;
    private StreamingShell $shell;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->server = new TestServer();
        $this->server->register(new EchoHandler());
        $this->server->register(new StreamHandler());

        $this->transport = new InMemoryStreamingTransport($this->server);
        $this->shell = new StreamingShell($this->transport, 'stream> ');
        $this->output = new BufferedOutput();
    }

    public function testStreamingModeActivation(): void
    {
        $this->transport->connect();

        self::assertFalse($this->transport->isStreaming());

        $this->transport->startStreaming();

        self::assertTrue($this->transport->isStreaming());

        $this->transport->stopStreaming();

        self::assertFalse($this->transport->isStreaming());
    }

    public function testMessageQueueing(): void
    {
        $this->transport->connect();

        $msg1 = Message::data(['count' => 1], 'sensor');
        $msg2 = Message::data(['count' => 2], 'sensor');

        $this->transport->queueMessage($msg1);
        $this->transport->queueMessage($msg2);

        self::assertSame(2, $this->transport->getQueueSize());

        $received1 = $this->transport->receive();
        self::assertNotNull($received1);
        self::assertIsArray($received1->payload);
        self::assertSame(1, $received1->payload['count']);

        $received2 = $this->transport->receive();
        self::assertNotNull($received2);
        self::assertIsArray($received2->payload);
        self::assertSame(2, $received2->payload['count']);

        $received3 = $this->transport->receive();
        self::assertNull($received3);

        self::assertSame(0, $this->transport->getQueueSize());
    }

    public function testMessageCallback(): void
    {
        $this->transport->connect();
        $this->transport->startStreaming();

        /** @var array<Message> $received */
        $received = [];
        $this->transport->onMessage(function (Message $message) use (&$received): void {
            $received[] = $message;
        });

        $this->transport->queueMessage(Message::data(['event' => 'tick'], 'timer'));
        $this->transport->queueMessage(Message::system('heartbeat'));

        self::assertCount(2, $received);
        self::assertIsArray($received[0]->payload);
        self::assertSame('tick', $received[0]->payload['event']);
        self::assertSame('heartbeat', $received[1]->payload);
    }

    public function testCallbackNotCalledWhenNotStreaming(): void
    {
        $this->transport->connect();
        // Not starting streaming mode

        $callCount = 0;
        $this->transport->onMessage(function () use (&$callCount): void {
            ++$callCount;
        });

        $this->transport->queueMessage(Message::data(['test' => true], 'source'));

        // Callback should not be invoked when not streaming
        self::assertSame(0, $callCount);

        // But message should still be in queue
        self::assertSame(1, $this->transport->getQueueSize());
    }

    public function testStreamCommandExecution(): void
    {
        $this->transport->connect();

        $exitCode = $this->shell->executeCommand('stream mqtt/events', $this->output);

        // StreamingShell sends commands async and outputs "Command sent"
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Command sent: stream', $this->output->fetch());
    }

    public function testRegularCommandInStreamingContext(): void
    {
        $this->transport->connect();
        $this->transport->startStreaming();

        $exitCode = $this->shell->executeCommand('echo streaming context', $this->output);

        // StreamingShell sends commands async and outputs "Command sent"
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Command sent: echo', $this->output->fetch());
    }

    public function testTransportInfo(): void
    {
        $this->transport->connect();
        $this->transport->queueMessage(Message::data([], 'test'));

        $info = $this->transport->getInfo();

        self::assertSame('in-memory-streaming', $info['type']);
        self::assertTrue($info['test']);
        self::assertTrue($info['connected']);
        self::assertSame(1, $info['queue_size']);
    }

    public function testSupportsStreaming(): void
    {
        self::assertTrue($this->transport->supportsStreaming());
    }

    public function testSendAsync(): void
    {
        $this->transport->connect();

        // sendAsync should not throw when connected
        $this->transport->sendAsync(new \NashGao\InteractiveShell\Parser\ParsedCommand(
            'echo',
            ['async', 'test'],
            [],
            'echo async test',
            false
        ));

        // Since it's in-memory, execution is synchronous
        // The important thing is it doesn't throw
        self::assertTrue(true);
    }

    public function testSendAsyncWhenNotConnected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not connected');

        $this->transport->sendAsync(new \NashGao\InteractiveShell\Parser\ParsedCommand(
            'echo',
            ['test'],
            [],
            'echo test',
            false
        ));
    }

    public function testStartStreamingWhenNotConnected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not connected');

        $this->transport->startStreaming();
    }

    public function testQueueMultipleMessages(): void
    {
        $this->transport->connect();

        $messages = [
            Message::data(['id' => 1], 'source'),
            Message::data(['id' => 2], 'source'),
            Message::data(['id' => 3], 'source'),
        ];

        $this->transport->queueMessages($messages);

        self::assertSame(3, $this->transport->getQueueSize());
    }

    public function testClearQueue(): void
    {
        $this->transport->connect();

        $this->transport->queueMessage(Message::data(['test' => true], 'source'));
        $this->transport->queueMessage(Message::data(['test' => true], 'source'));

        self::assertSame(2, $this->transport->getQueueSize());

        $this->transport->clearQueue();

        self::assertSame(0, $this->transport->getQueueSize());
        self::assertNull($this->transport->receive());
    }

    public function testDisconnectClearsStreamingState(): void
    {
        $this->transport->connect();
        $this->transport->startStreaming();
        $this->transport->queueMessage(Message::data([], 'test'));

        $this->transport->disconnect();

        self::assertFalse($this->transport->isConnected());
        self::assertFalse($this->transport->isStreaming());
        self::assertSame(0, $this->transport->getQueueSize());
    }

    public function testReceiveWhenNotConnected(): void
    {
        $message = $this->transport->receive();

        self::assertNull($message);
    }

    public function testMessageTypes(): void
    {
        $this->transport->connect();

        // Queue different message types
        $this->transport->queueMessage(Message::data(['key' => 'value'], 'data-source'));
        $this->transport->queueMessage(Message::system('System notification'));
        $this->transport->queueMessage(Message::error('Something went wrong'));

        $msg1 = $this->transport->receive();
        self::assertNotNull($msg1);
        self::assertSame('data', $msg1->type);
        self::assertSame('data-source', $msg1->source);

        $msg2 = $this->transport->receive();
        self::assertSame('system', $msg2?->type);

        $msg3 = $this->transport->receive();
        self::assertSame('error', $msg3?->type);
    }
}
