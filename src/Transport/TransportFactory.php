<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Transport;

use RuntimeException;
use Swoole\Coroutine;

/**
 * Factory for creating transport instances with automatic context detection.
 *
 * Selects the appropriate transport implementation based on the execution
 * context (e.g., Swoole coroutine for pooled transports).
 */
final class TransportFactory
{
    /**
     * Create a Unix socket transport.
     *
     * Returns SwooleSocketTransport for coroutine-aware socket communication.
     */
    public static function unix(
        string $socketPath,
        float $timeout = 0.0,
    ): TransportInterface {
        return new SwooleSocketTransport($socketPath, $timeout);
    }

    /**
     * Create a pooled Swoole transport for high-concurrency scenarios.
     *
     * Requires Swoole coroutine context as connection pooling relies on
     * coroutine channels for synchronization.
     *
     * @throws RuntimeException When not in Swoole coroutine context
     */
    public static function pooled(
        string $socketPath,
        int $poolSize = 10,
        float $timeout = 0.0,
    ): StreamingTransportInterface {
        if (! self::inSwooleCoroutine()) {
            throw new RuntimeException('Pooled transport requires Swoole coroutine context');
        }

        return new PooledSwooleTransport($socketPath, $poolSize, $timeout);
    }

    /**
     * Create a Swoole socket transport explicitly.
     */
    public static function swoole(
        string $socketPath,
        float $timeout = 0.0,
    ): SwooleSocketTransport {
        return new SwooleSocketTransport($socketPath, $timeout);
    }

    /**
     * Check if currently running inside a Swoole coroutine.
     */
    public static function inSwooleCoroutine(): bool
    {
        return self::swooleAvailable()
            && class_exists(Coroutine::class)
            && Coroutine::getCid() > 0;
    }

    /**
     * Check if Swoole extension is available.
     */
    public static function swooleAvailable(): bool
    {
        return true;
    }

    /**
     * Get information about the current execution context.
     *
     * @return array<string, mixed>
     */
    public static function getContextInfo(): array
    {
        return [
            'swoole_available' => self::swooleAvailable(),
            'in_coroutine' => self::inSwooleCoroutine(),
            'coroutine_id' => self::inSwooleCoroutine() ? Coroutine::getCid() : null,
            'recommended_transport' => self::getRecommendedTransport(),
        ];
    }

    /**
     * Get the recommended transport type for the current context.
     */
    private static function getRecommendedTransport(): string
    {
        return 'swoole_socket';
    }
}
