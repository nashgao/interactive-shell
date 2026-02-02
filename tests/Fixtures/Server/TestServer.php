<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Server;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;

/**
 * Test server that dispatches commands in-memory without network.
 *
 * This server provides the same command execution semantics as a real
 * SocketServer but without the socket layer, making it ideal for:
 * - Fast integration tests
 * - Testing command handler logic
 * - Debugging command flows
 *
 * Example usage:
 * ```php
 * // Create server with default context
 * $server = new TestServer();
 * $server->register(new EchoHandler());
 * $server->register(new ErrorHandler());
 *
 * // Execute commands
 * $result = $server->dispatch(new ParsedCommand('echo', ['hello', 'world'], [], 'echo hello world', false));
 * // $result->success === true
 * // $result->data === 'hello world'
 *
 * // With custom context
 * $context = new TestContext(['debug' => true]);
 * $server = new TestServer($context);
 *
 * // Chain registrations
 * $server
 *     ->register(new EchoHandler())
 *     ->register(new DelayHandler())
 *     ->register(new StreamHandler());
 * ```
 *
 * @see InMemoryTransport For using this server with Shell
 */
final class TestServer
{
    private CommandRegistry $registry;

    public function __construct(
        private readonly TestContext $context = new TestContext(),
    ) {
        $this->registry = new CommandRegistry();
    }

    /**
     * Register a command handler.
     *
     * @return $this For method chaining
     */
    public function register(CommandHandlerInterface $handler): self
    {
        $this->registry->register($handler);
        return $this;
    }

    /**
     * Dispatch a parsed command to its handler.
     *
     * @param ParsedCommand $command The command to execute
     * @return CommandResult The execution result
     */
    public function dispatch(ParsedCommand $command): CommandResult
    {
        return $this->registry->execute($command, $this->context);
    }

    /**
     * Get the underlying command registry.
     *
     * Useful for inspecting registered handlers or advanced configuration.
     */
    public function getRegistry(): CommandRegistry
    {
        return $this->registry;
    }

    /**
     * Get the context used by this server.
     */
    public function getContext(): TestContext
    {
        return $this->context;
    }

    /**
     * Check if a command is registered.
     */
    public function hasCommand(string $command): bool
    {
        return $this->registry->has($command);
    }

    /**
     * Get list of registered command names.
     *
     * @return array<string>
     */
    public function getCommands(): array
    {
        return $this->registry->getCommandList();
    }
}
