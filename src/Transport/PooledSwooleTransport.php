<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Transport;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use RuntimeException;

/**
 * Pooled Swoole transport wrapper for transparent connection pool usage.
 *
 * Automatically acquires/releases connections from the pool for each operation,
 * enabling high-concurrency scenarios with connection reuse.
 */
final class PooledSwooleTransport implements StreamingTransportInterface
{
    private SocketConnectionPool $pool;
    private ?SwooleSocketTransport $activeTransport = null;
    private bool $streaming = false;
    /** @var callable(Message): void|null */
    private $messageCallback = null;

    public function __construct(
        string $socketPath,
        int $poolSize = 10,
        float $timeout = 0.0,
    ) {
        $this->pool = new SocketConnectionPool($socketPath, $poolSize, $timeout);
    }

    public function connect(): void
    {
        // Pool manages connections - nothing to do here
        // Connections are created on-demand when needed
    }

    public function disconnect(): void
    {
        $this->releaseActiveTransport();
        $this->pool->close();
    }

    public function isConnected(): bool
    {
        // Pooled transport is always "connected" as long as pool is open
        return ! $this->pool->isClosed();
    }

    public function send(ParsedCommand $command): CommandResult
    {
        $transport = $this->pool->get();
        try {
            return $transport->send($command);
        } finally {
            $this->pool->put($transport);
        }
    }

    public function ping(): bool
    {
        try {
            $transport = $this->pool->get();
            try {
                return $transport->ping();
            } finally {
                $this->pool->put($transport);
            }
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return [
            'type' => 'pooled_swoole_socket',
            'pool_size' => $this->pool->getMaxSize(),
            'current_connections' => $this->pool->getCurrentSize(),
            'available_connections' => $this->pool->getAvailableCount(),
            'streaming' => $this->streaming,
        ];
    }

    public function getEndpoint(): string
    {
        return 'pooled+swoole+unix://';
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function sendAsync(ParsedCommand $command): void
    {
        $this->ensureActiveTransport();

        if ($this->activeTransport === null) {
            throw new RuntimeException('Failed to acquire transport for async operation');
        }

        $this->activeTransport->sendAsync($command);
    }

    public function receive(float $timeout = -1): ?Message
    {
        $this->ensureActiveTransport();

        if ($this->activeTransport === null) {
            return null;
        }

        return $this->activeTransport->receive($timeout);
    }

    public function onMessage(callable $callback): void
    {
        $this->messageCallback = $callback;

        if ($this->activeTransport !== null) {
            $this->activeTransport->onMessage($callback);
        }
    }

    public function startStreaming(): void
    {
        $this->ensureActiveTransport();

        if ($this->activeTransport === null) {
            throw new RuntimeException('Failed to acquire transport for streaming');
        }

        if ($this->messageCallback !== null) {
            $this->activeTransport->onMessage($this->messageCallback);
        }

        $this->activeTransport->startStreaming();
        $this->streaming = true;
    }

    public function stopStreaming(): void
    {
        if ($this->activeTransport !== null) {
            $this->activeTransport->stopStreaming();
        }

        $this->streaming = false;
        $this->releaseActiveTransport();
    }

    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * Get the underlying connection pool for advanced operations.
     */
    public function getPool(): SocketConnectionPool
    {
        return $this->pool;
    }

    /**
     * Ensure an active transport is acquired for streaming operations.
     */
    private function ensureActiveTransport(): void
    {
        if ($this->activeTransport === null || ! $this->activeTransport->isConnected()) {
            $this->activeTransport = $this->pool->get();
        }
    }

    /**
     * Release the active transport back to the pool.
     */
    private function releaseActiveTransport(): void
    {
        if ($this->activeTransport !== null) {
            $this->pool->put($this->activeTransport);
            $this->activeTransport = null;
        }
    }
}
