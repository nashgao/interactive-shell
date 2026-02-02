<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Transport;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestServer;
use NashGao\InteractiveShell\Transport\TransportInterface;

/**
 * In-memory transport for fast integration testing.
 *
 * This transport connects directly to a TestServer instance without any
 * network overhead, enabling rapid test execution while still exercising
 * the full command dispatch pipeline.
 *
 * Key characteristics:
 * - Synchronous request/response (no network latency)
 * - Deterministic behavior for reliable tests
 * - Same interface as real transports (UnixSocketTransport, HttpTransport)
 *
 * Example usage:
 * ```php
 * // Setup test server with handlers
 * $server = new TestServer();
 * $server->register(new EchoHandler());
 * $server->register(new ErrorHandler());
 *
 * // Create transport and shell
 * $transport = new InMemoryTransport($server);
 * $shell = new Shell($transport, 'test> ');
 *
 * // Connect and execute
 * $transport->connect();
 * $result = $shell->executeCommand('echo hello world', $output);
 *
 * // Direct transport usage (without Shell)
 * $transport->connect();
 * $command = new ParsedCommand('echo', ['hello'], [], 'echo hello', false);
 * $result = $transport->send($command);
 * assert($result->success === true);
 * assert($result->data === 'hello');
 * ```
 *
 * @see TestServer For command registration and dispatch
 * @see Shell For interactive shell usage
 */
final class InMemoryTransport implements TransportInterface
{
    private bool $connected = false;

    public function __construct(
        private readonly TestServer $server,
    ) {}

    public function connect(): void
    {
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
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
            'type' => 'in-memory',
            'test' => true,
            'connected' => $this->connected,
            'commands' => $this->server->getCommands(),
        ];
    }

    public function getEndpoint(): string
    {
        return 'memory://test-server';
    }

    /**
     * Get the underlying test server.
     *
     * Useful for inspecting server state or registering additional handlers
     * during tests.
     */
    public function getServer(): TestServer
    {
        return $this->server;
    }
}
