<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;

/**
 * Registry that routes commands to their handlers.
 *
 * Thread-safe and coroutine-safe for use in Swoole environments.
 */
final class CommandRegistry
{
    /** @var array<string, CommandHandlerInterface> */
    private array $handlers = [];

    /** Fallback handler for commands not found in the registry */
    private ?CommandHandlerInterface $fallbackHandler = null;

    /**
     * Register a command handler.
     *
     * @param CommandHandlerInterface $handler The handler to register
     * @return self For method chaining
     */
    public function register(CommandHandlerInterface $handler): self
    {
        $this->handlers[$handler->getCommand()] = $handler;
        return $this;
    }

    /**
     * Register multiple handlers at once.
     *
     * @param iterable<CommandHandlerInterface> $handlers
     * @return self For method chaining
     */
    public function registerMany(iterable $handlers): self
    {
        foreach ($handlers as $handler) {
            $this->register($handler);
        }
        return $this;
    }

    /**
     * Set a fallback handler for commands not found in the registry.
     *
     * The fallback handler is invoked when no exact match is found.
     *
     * @param CommandHandlerInterface $handler The fallback handler
     * @return self For method chaining
     */
    public function setFallbackHandler(CommandHandlerInterface $handler): self
    {
        $this->fallbackHandler = $handler;
        return $this;
    }

    /**
     * Get the fallback handler.
     *
     * @return CommandHandlerInterface|null The fallback handler or null
     */
    public function getFallbackHandler(): ?CommandHandlerInterface
    {
        return $this->fallbackHandler;
    }

    /**
     * Check if a command has a registered handler.
     *
     * @param string $command The command name
     */
    public function has(string $command): bool
    {
        return isset($this->handlers[$command]);
    }

    /**
     * Get a handler by command name.
     *
     * @param string $command The command name
     * @return CommandHandlerInterface|null The handler or null if not found
     */
    public function get(string $command): ?CommandHandlerInterface
    {
        return $this->handlers[$command] ?? null;
    }

    /**
     * Execute a command using the appropriate handler.
     *
     * If no exact match is found and a fallback handler is set,
     * the fallback handler will be invoked.
     *
     * @param ParsedCommand $command The parsed command
     * @param ContextInterface $context The framework context
     * @return CommandResult The execution result
     */
    public function execute(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $handler = $this->get($command->command);

        // Use fallback handler if no exact match found
        if ($handler === null && $this->fallbackHandler !== null) {
            $handler = $this->fallbackHandler;
        }

        if ($handler === null) {
            return CommandResult::failure(
                sprintf("Unknown command: '%s'. Type 'help' for available commands.", $command->command),
                ['available' => $this->getCommandList()]
            );
        }

        return $handler->handle($command, $context);
    }

    /**
     * Get list of all registered command names.
     *
     * @return array<string>
     */
    public function getCommandList(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * Get all registered handlers.
     *
     * @return array<string, CommandHandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Get command descriptions for help output.
     *
     * @return array<string, string> Command name => description
     */
    public function getCommandDescriptions(): array
    {
        $descriptions = [];
        foreach ($this->handlers as $command => $handler) {
            $descriptions[$command] = $handler->getDescription();
        }
        ksort($descriptions);
        return $descriptions;
    }

    /**
     * Remove a handler by command name.
     *
     * @param string $command The command to remove
     * @return bool True if removed, false if not found
     */
    public function remove(string $command): bool
    {
        if (!isset($this->handlers[$command])) {
            return false;
        }
        unset($this->handlers[$command]);
        return true;
    }

    /**
     * Clear all registered handlers.
     */
    public function clear(): void
    {
        $this->handlers = [];
    }

    /**
     * Get the number of registered handlers.
     */
    public function count(): int
    {
        return count($this->handlers);
    }
}
