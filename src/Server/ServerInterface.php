<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server;

/**
 * Interface for shell server implementations.
 *
 * A server listens for incoming shell connections and processes commands.
 */
interface ServerInterface
{
    /**
     * Start the server and begin accepting connections.
     *
     * This method typically blocks until stop() is called.
     */
    public function start(): void;

    /**
     * Stop the server and close all connections.
     */
    public function stop(): void;

    /**
     * Check if the server is currently running.
     */
    public function isRunning(): bool;

    /**
     * Get the endpoint the server is listening on.
     *
     * @return string The socket path or address
     */
    public function getEndpoint(): string;
}
