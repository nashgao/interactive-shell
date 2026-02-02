<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Transport;

use RuntimeException;
use Swoole\Coroutine;

/**
 * Factory for creating transport instances with automatic context detection.
 *
 * Selects the appropriate transport implementation based on the execution
 * context (Swoole coroutine vs. standard PHP).
 */
final class TransportFactory
{
    /**
     * Create a Unix socket transport with automatic Swoole detection.
     *
     * Returns SwooleSocketTransport when running in a Swoole coroutine context,
     * otherwise returns UnixSocketTransport.
     */
    public static function unix(
        string $socketPath,
        float $timeout = 0.0,
    ): TransportInterface {
        if (self::inSwooleCoroutine()) {
            return new SwooleSocketTransport($socketPath, $timeout);
        }

        return new UnixSocketTransport($socketPath, $timeout);
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
     *
     * Use this when you want to ensure Swoole transport is used,
     * regardless of auto-detection.
     *
     * @throws RuntimeException When Swoole extension is not loaded
     */
    public static function swoole(
        string $socketPath,
        float $timeout = 0.0,
    ): SwooleSocketTransport {
        if (! self::swooleAvailable()) {
            throw new RuntimeException('Swoole extension is not loaded');
        }

        return new SwooleSocketTransport($socketPath, $timeout);
    }

    /**
     * Create an HTTP transport.
     */
    public static function http(
        string $baseUrl,
        float $timeout = 0.0,
    ): TransportInterface {
        return new HttpTransport($baseUrl, $timeout);
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
        return extension_loaded('swoole');
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
        if (self::inSwooleCoroutine()) {
            return 'swoole_socket';
        }

        return 'unix_socket';
    }
}
