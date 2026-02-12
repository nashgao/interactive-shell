<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Transport;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestServer;
use NashGao\InteractiveShell\Transport\StreamingTransportInterface;

/**
 * In-memory streaming transport for testing StreamingShell.
 *
 * This transport provides the same interface as SwooleSocketTransport but
 * operates entirely in memory. Messages can be queued programmatically
 * and will be delivered to registered callbacks or via receive().
 *
 * Key characteristics:
 * - Full StreamingTransportInterface implementation
 * - Queue-based message delivery
 * - Synchronous message callback invocation
 * - No network dependencies
 *
 * Example usage:
 * ```php
 * // Setup
 * $server = new TestServer();
 * $server->register(new StreamHandler());
 *
 * $transport = new InMemoryStreamingTransport($server);
 * $transport->connect();
 *
 * // Queue messages for consumption
 * $transport->queueMessage(Message::data(['count' => 1], 'sensor'));
 * $transport->queueMessage(Message::data(['count' => 2], 'sensor'));
 *
 * // Consume via receive()
 * $msg1 = $transport->receive();  // Returns first message
 * $msg2 = $transport->receive();  // Returns second message
 * $msg3 = $transport->receive();  // Returns null (queue empty)
 *
 * // Or use callback-based consumption
 * $received = [];
 * $transport->onMessage(function(Message $m) use (&$received) {
 *     $received[] = $m;
 * });
 * $transport->queueMessage(Message::system('test'));  // Callback invoked immediately
 *
 * // With StreamingShell
 * $shell = new StreamingShell($transport, 'stream> ');
 * $transport->queueMessage(Message::data(['event' => 'tick'], 'timer'));
 * ```
 *
 * @see TestServer For command handling
 * @see StreamingShell For streaming shell usage
 */
final class InMemoryStreamingTransport implements StreamingTransportInterface
{
    private bool $connected = false;
    private bool $streaming = false;

    /** @var array<Message> */
    private array $messageQueue = [];

    /** @var callable(Message): void|null */
    private $messageCallback = null;

    public function __construct(
        private readonly TestServer $server,
    ) {}

    // === TransportInterface methods ===

    public function connect(): void
    {
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
        $this->streaming = false;
        $this->messageQueue = [];
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function send(ParsedCommand $command): CommandResult
    {
        if (!$this->connected) {
            return CommandResult::failure('Not connected to server');
        }

        return $this->server->dispatch($command);
    }

    public function ping(): bool
    {
        return $this->connected;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return [
            'type' => 'in-memory-streaming',
            'test' => true,
            'connected' => $this->connected,
            'streaming' => $this->streaming,
            'queue_size' => count($this->messageQueue),
        ];
    }

    public function getEndpoint(): string
    {
        return 'memory://streaming-test-server';
    }

    // === StreamingTransportInterface methods ===

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function sendAsync(ParsedCommand $command): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('Not connected to server');
        }

        // In test mode, async is effectively sync
        $this->server->dispatch($command);
    }

    public function receive(float $timeout = -1): ?Message
    {
        if (!$this->connected) {
            return null;
        }

        return array_shift($this->messageQueue);
    }

    public function onMessage(callable $callback): void
    {
        $this->messageCallback = $callback;
    }

    public function startStreaming(): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('Not connected to server');
        }

        $this->streaming = true;
    }

    public function stopStreaming(): void
    {
        $this->streaming = false;
    }

    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    // === Test helper methods ===

    /**
     * Queue a message for consumption.
     *
     * If a message callback is registered, it will be invoked immediately.
     * Otherwise, the message is added to the queue for later receive() calls.
     *
     * @param Message $message The message to queue
     */
    public function queueMessage(Message $message): void
    {
        $this->messageQueue[] = $message;

        // Invoke callback if registered (simulates async delivery)
        if ($this->messageCallback !== null && $this->streaming) {
            ($this->messageCallback)($message);
        }
    }

    /**
     * Queue multiple messages at once.
     *
     * @param array<Message> $messages Messages to queue
     */
    public function queueMessages(array $messages): void
    {
        foreach ($messages as $message) {
            $this->queueMessage($message);
        }
    }

    /**
     * Get the number of messages in the queue.
     */
    public function getQueueSize(): int
    {
        return count($this->messageQueue);
    }

    /**
     * Clear all queued messages.
     */
    public function clearQueue(): void
    {
        $this->messageQueue = [];
    }

    /**
     * Get the underlying test server.
     */
    public function getServer(): TestServer
    {
        return $this->server;
    }
}
