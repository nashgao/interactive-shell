<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Transport;

use RuntimeException;
use Swoole\Coroutine\Channel;

/**
 * Connection pool for managing reusable Swoole socket connections.
 *
 * Uses Swoole\Coroutine\Channel for coroutine-safe connection pooling,
 * enabling high-throughput concurrent operations with limited connections.
 */
final class SocketConnectionPool
{
    private Channel $pool;
    private int $currentSize = 0;
    private bool $closed = false;

    public function __construct(
        private readonly string $socketPath,
        private readonly int $maxSize = 10,
        private readonly float $timeout = 0.0,
    ) {
        $this->pool = new Channel($maxSize);
    }

    /**
     * Get a connection from the pool.
     *
     * Returns an existing connection if available, creates a new one if under
     * the limit, or waits for an available connection.
     */
    public function get(): SwooleSocketTransport
    {
        if ($this->closed) {
            throw new RuntimeException('Connection pool is closed');
        }

        // Try to get an existing connection (non-blocking)
        if (! $this->pool->isEmpty()) {
            /** @var SwooleSocketTransport $transport */
            $transport = $this->pool->pop(0.001);
            if ($transport !== false && $transport->isConnected()) {
                return $transport;
            }
            // Connection is stale, decrement counter and try again
            if ($transport !== false) {
                $this->currentSize--;
            }
        }

        // Create new connection if under limit
        if ($this->currentSize < $this->maxSize) {
            return $this->createConnection();
        }

        // Wait for available connection with timeout (default 30s to prevent indefinite blocking)
        /** @var SwooleSocketTransport|false $transport */
        $transport = $this->pool->pop($this->timeout > 0 ? $this->timeout : 30.0);

        if ($transport === false) {
            throw new RuntimeException('Timeout waiting for available connection from pool');
        }

        // Validate connection is still alive
        if (! $transport->isConnected()) {
            $this->currentSize--;
            return $this->createConnection();
        }

        return $transport;
    }

    /**
     * Return a connection to the pool.
     *
     * If the connection is still valid, it's added back to the pool.
     * If disconnected, the pool size is decremented.
     */
    public function put(SwooleSocketTransport $transport): void
    {
        if ($this->closed) {
            $transport->disconnect();
            return;
        }

        if ($transport->isConnected()) {
            // Stop streaming mode before returning to pool
            if ($transport->isStreaming()) {
                $transport->stopStreaming();
            }

            $this->pool->push($transport, 0.001);
        } else {
            $this->currentSize--;
        }
    }

    /**
     * Close all connections and shut down the pool.
     */
    public function close(): void
    {
        $this->closed = true;

        while (! $this->pool->isEmpty()) {
            /** @var SwooleSocketTransport|false $transport */
            $transport = $this->pool->pop(0.001);
            if ($transport !== false) {
                $transport->disconnect();
                $this->currentSize--;
            }
        }

        $this->pool->close();
    }

    /**
     * Get the current number of connections in the pool.
     */
    public function getCurrentSize(): int
    {
        return $this->currentSize;
    }

    /**
     * Get the maximum pool size.
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * Get the number of connections currently available in the pool.
     */
    public function getAvailableCount(): int
    {
        /** @var int $length */
        $length = $this->pool->length();
        return $length;
    }

    /**
     * Check if the pool is closed.
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Create a new connection and add it to the pool count.
     */
    private function createConnection(): SwooleSocketTransport
    {
        $transport = new SwooleSocketTransport($this->socketPath, $this->timeout);
        $transport->connect();
        $this->currentSize++;
        return $transport;
    }
}
