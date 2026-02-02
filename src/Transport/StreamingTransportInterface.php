<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Transport;

use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Parser\ParsedCommand;

/**
 * Extended transport interface for bidirectional streaming communication.
 */
interface StreamingTransportInterface extends TransportInterface
{
    /**
     * Check if this transport supports streaming mode.
     */
    public function supportsStreaming(): bool;

    /**
     * Send a command without waiting for response (async mode).
     */
    public function sendAsync(ParsedCommand $command): void;

    /**
     * Receive a message from the stream (may block).
     *
     * @param float $timeout Timeout in seconds (0 = non-blocking, -1 = infinite)
     */
    public function receive(float $timeout = -1): ?Message;

    /**
     * Register a callback for incoming messages.
     *
     * @param callable(Message): void $callback
     */
    public function onMessage(callable $callback): void;

    /**
     * Start the streaming message loop.
     */
    public function startStreaming(): void;

    /**
     * Stop the streaming message loop.
     */
    public function stopStreaming(): void;

    /**
     * Check if currently in streaming mode.
     */
    public function isStreaming(): bool;
}
