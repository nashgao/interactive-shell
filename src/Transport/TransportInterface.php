<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Transport;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;

interface TransportInterface
{
    /**
     * Establish connection to the remote endpoint.
     */
    public function connect(): void;

    /**
     * Close the connection.
     */
    public function disconnect(): void;

    /**
     * Check if currently connected.
     */
    public function isConnected(): bool;

    /**
     * Send a command and receive the response (request/response mode).
     */
    public function send(ParsedCommand $command): CommandResult;

    /**
     * Check if the remote endpoint is reachable.
     */
    public function ping(): bool;

    /**
     * Get information about the remote endpoint.
     *
     * @return array<string, mixed>
     */
    public function getInfo(): array;

    /**
     * Get the endpoint URL/address.
     */
    public function getEndpoint(): string;
}
