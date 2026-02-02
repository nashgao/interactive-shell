<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Specification;

use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\StreamingShell;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\StreamHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestServer;
use NashGao\InteractiveShell\Tests\Fixtures\Transport\InMemoryStreamingTransport;
use PHPUnit\Framework\TestCase;

/**
 * Streaming Consumer Specification Tests.
 *
 * These tests define expected behavior from the STREAMING CONSUMER's perspective.
 * A streaming consumer is someone who:
 * - Uses StreamingShell or StreamingTransportInterface
 * - Receives messages asynchronously
 * - Can filter and pause message streams
 *
 * SPECIFICATION-FIRST: These tests define what a streaming consumer expects,
 * NOT what the implementation currently does.
 *
 * Pre-Test Checklist:
 * - [x] Testing from the consumer's perspective
 * - [x] Tests would fail if the feature was broken
 * - [x] Written WITHOUT reading implementation first
 * - [x] Test names describe requirements, not implementation
 */
final class StreamingConsumerSpecTest extends TestCase
{
    private TestServer $server;
    private InMemoryStreamingTransport $transport;

    protected function setUp(): void
    {
        $this->server = new TestServer();
        $this->server->register(new StreamHandler());

        $this->transport = new InMemoryStreamingTransport($this->server);
    }

    /**
     * SPECIFICATION: A consumer can receive streaming messages after connecting.
     */
    public function testConsumerCanReceiveStreamingMessages(): void
    {
        // Given: Consumer connects and starts streaming
        $this->transport->connect();
        $this->transport->startStreaming();

        // When: Messages arrive from the server
        $this->transport->queueMessage(Message::data(['count' => 1], 'sensor'));
        $this->transport->queueMessage(Message::data(['count' => 2], 'sensor'));

        // Then: Consumer can receive messages
        $msg1 = $this->transport->receive();
        $msg2 = $this->transport->receive();

        self::assertNotNull($msg1, 'Should receive first message');
        self::assertNotNull($msg2, 'Should receive second message');
        self::assertSame('sensor', $msg1->source);
        self::assertSame(['count' => 1], $msg1->payload);
        self::assertSame(['count' => 2], $msg2->payload);
    }

    /**
     * SPECIFICATION: Consumer receives null when no messages are available.
     */
    public function testConsumerReceivesNullWhenNoMessages(): void
    {
        // Given: Consumer is connected with empty queue
        $this->transport->connect();

        // When: Consumer tries to receive
        $msg = $this->transport->receive();

        // Then: Null is returned (no blocking)
        self::assertNull($msg, 'Should return null when queue is empty');
    }

    /**
     * SPECIFICATION: Consumer can register a callback to receive messages.
     */
    public function testConsumerCanRegisterMessageCallback(): void
    {
        // Given: Consumer registers a callback
        $received = [];
        $this->transport->onMessage(function (Message $m) use (&$received): void {
            $received[] = $m;
        });

        $this->transport->connect();
        $this->transport->startStreaming();

        // When: Messages arrive
        $this->transport->queueMessage(Message::data(['event' => 'tick'], 'timer'));
        $this->transport->queueMessage(Message::data(['event' => 'tock'], 'timer'));

        // Then: Callback is invoked for each message
        self::assertCount(2, $received, 'Callback should receive all messages');
        self::assertIsArray($received[0]->payload);
        self::assertIsArray($received[1]->payload);
        self::assertSame('tick', $received[0]->payload['event']);
        self::assertSame('tock', $received[1]->payload['event']);
    }

    /**
     * SPECIFICATION: Consumer can start and stop streaming.
     */
    public function testConsumerCanStartAndStopStreaming(): void
    {
        // Given: Consumer is connected
        $this->transport->connect();

        // Initially not streaming
        self::assertFalse($this->transport->isStreaming());

        // When: Consumer starts streaming
        $this->transport->startStreaming();

        // Then: Streaming is active
        self::assertTrue($this->transport->isStreaming());

        // When: Consumer stops streaming
        $this->transport->stopStreaming();

        // Then: Streaming is stopped
        self::assertFalse($this->transport->isStreaming());
    }

    /**
     * SPECIFICATION: Consumer can check if transport supports streaming.
     */
    public function testConsumerCanCheckStreamingSupport(): void
    {
        // Given: A streaming transport
        // When: Consumer checks streaming support
        $supports = $this->transport->supportsStreaming();

        // Then: True is returned
        self::assertTrue($supports, 'Streaming transport should support streaming');
    }

    /**
     * SPECIFICATION: Different message types are distinguishable.
     */
    public function testConsumerCanDistinguishMessageTypes(): void
    {
        // Given: Consumer is receiving messages
        $this->transport->connect();

        // When: Different message types arrive
        $this->transport->queueMessage(Message::data(['value' => 42], 'sensor'));
        $this->transport->queueMessage(Message::system('Server restarting'));
        $this->transport->queueMessage(Message::error('Connection lost'));

        $dataMsg = $this->transport->receive();
        $systemMsg = $this->transport->receive();
        $errorMsg = $this->transport->receive();

        // Then: Types are distinguishable
        self::assertNotNull($dataMsg);
        self::assertNotNull($systemMsg);
        self::assertNotNull($errorMsg);
        self::assertSame('data', $dataMsg->type);
        self::assertSame('system', $systemMsg->type);
        self::assertSame('error', $errorMsg->type);
    }

    /**
     * SPECIFICATION: Messages include timestamps for ordering.
     */
    public function testMessagesIncludeTimestamps(): void
    {
        // Given: Consumer receives a message
        $this->transport->connect();
        $this->transport->queueMessage(Message::data(['test' => true], 'source'));

        // When: Consumer reads the message
        $msg = $this->transport->receive();

        // Then: Timestamp is present and valid
        self::assertNotNull($msg);
        self::assertInstanceOf(\DateTimeImmutable::class, $msg->timestamp);
    }

    /**
     * SPECIFICATION: Messages include source information.
     */
    public function testMessagesIncludeSourceInformation(): void
    {
        // Given: Messages from different sources
        $this->transport->connect();
        $this->transport->queueMessage(Message::data(['temp' => 25], 'sensor/temperature'));
        $this->transport->queueMessage(Message::data(['humid' => 60], 'sensor/humidity'));

        // When: Consumer reads messages
        $msg1 = $this->transport->receive();
        $msg2 = $this->transport->receive();

        // Then: Source is identifiable
        self::assertNotNull($msg1);
        self::assertNotNull($msg2);
        self::assertSame('sensor/temperature', $msg1->source);
        self::assertSame('sensor/humidity', $msg2->source);
    }

    /**
     * SPECIFICATION: Consumer can get queue size for monitoring.
     */
    public function testConsumerCanGetQueueSizeForMonitoring(): void
    {
        // Given: Messages are queued
        $this->transport->connect();
        $this->transport->queueMessage(Message::data(1, 'test'));
        $this->transport->queueMessage(Message::data(2, 'test'));
        $this->transport->queueMessage(Message::data(3, 'test'));

        // When: Consumer checks queue size
        $size = $this->transport->getQueueSize();

        // Then: Size reflects queued messages
        self::assertSame(3, $size);

        // When: Consumer consumes one message
        $this->transport->receive();

        // Then: Size decreases
        self::assertSame(2, $this->transport->getQueueSize());
    }

    /**
     * SPECIFICATION: Consumer can clear the message queue.
     */
    public function testConsumerCanClearMessageQueue(): void
    {
        // Given: Messages are queued
        $this->transport->connect();
        $this->transport->queueMessage(Message::data(1, 'test'));
        $this->transport->queueMessage(Message::data(2, 'test'));
        self::assertSame(2, $this->transport->getQueueSize());

        // When: Consumer clears the queue
        $this->transport->clearQueue();

        // Then: Queue is empty
        self::assertSame(0, $this->transport->getQueueSize());
        self::assertNull($this->transport->receive());
    }

    /**
     * SPECIFICATION: Disconnecting clears the message queue.
     */
    public function testDisconnectingClearsMessageQueue(): void
    {
        // Given: Messages are queued
        $this->transport->connect();
        $this->transport->queueMessage(Message::data(1, 'test'));
        self::assertSame(1, $this->transport->getQueueSize());

        // When: Consumer disconnects
        $this->transport->disconnect();

        // Then: Queue is cleared
        self::assertSame(0, $this->transport->getQueueSize());
    }

    /**
     * SPECIFICATION: Consumer can get transport info including streaming state.
     */
    public function testConsumerCanGetStreamingTransportInfo(): void
    {
        // Given: Consumer is streaming
        $this->transport->connect();
        $this->transport->startStreaming();
        $this->transport->queueMessage(Message::data(1, 'test'));

        // When: Consumer requests info
        $info = $this->transport->getInfo();

        // Then: Streaming-specific info is included
        self::assertArrayHasKey('streaming', $info);
        self::assertTrue($info['streaming']);
        self::assertArrayHasKey('queue_size', $info);
        self::assertSame(1, $info['queue_size']);
    }

    /**
     * SPECIFICATION: Consumer must be connected to start streaming.
     */
    public function testConsumerMustBeConnectedToStartStreaming(): void
    {
        // Given: Consumer is NOT connected
        // When: Consumer tries to start streaming
        // Then: Exception is thrown
        $this->expectException(\RuntimeException::class);
        $this->transport->startStreaming();
    }

    /**
     * SPECIFICATION: Messages can include metadata for additional context.
     */
    public function testMessagesCanIncludeMetadata(): void
    {
        // Given: Message with metadata
        $this->transport->connect();
        $this->transport->queueMessage(Message::data(
            ['value' => 100],
            'sensor',
            ['unit' => 'celsius', 'device_id' => 'temp-001']
        ));

        // When: Consumer reads the message
        $msg = $this->transport->receive();

        // Then: Metadata is accessible
        self::assertNotNull($msg);
        self::assertArrayHasKey('unit', $msg->metadata);
        self::assertSame('celsius', $msg->metadata['unit']);
        self::assertSame('temp-001', $msg->metadata['device_id']);
    }

    /**
     * SPECIFICATION: Consumer can convert messages to array format.
     */
    public function testMessagesCanBeConvertedToArray(): void
    {
        // Given: A message
        $this->transport->connect();
        $this->transport->queueMessage(Message::data(['test' => true], 'source'));

        // When: Consumer converts to array
        $msg = $this->transport->receive();
        self::assertNotNull($msg);
        $array = $msg->toArray();

        // Then: All fields are present
        self::assertIsArray($array);
        self::assertArrayHasKey('type', $array);
        self::assertArrayHasKey('payload', $array);
        self::assertArrayHasKey('source', $array);
        self::assertArrayHasKey('timestamp', $array);
        self::assertArrayHasKey('metadata', $array);
    }
}
