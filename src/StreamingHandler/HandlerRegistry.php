<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\StreamingHandler;

use NashGao\InteractiveShell\Parser\ParsedCommand;

/**
 * Registry that routes commands to their streaming handlers.
 *
 * Supports multiple command aliases per handler and provides
 * help/description lookup for shell UX.
 */
final class HandlerRegistry
{
    /** @var array<string, HandlerInterface> Command to handler mapping */
    private array $commandMap = [];

    /** @var array<HandlerInterface> All registered handlers (deduplicated) */
    private array $handlers = [];

    /**
     * Register a handler for its commands.
     *
     * @param HandlerInterface $handler The handler to register
     * @return self For method chaining
     */
    public function register(HandlerInterface $handler): self
    {
        foreach ($handler->getCommands() as $command) {
            $this->commandMap[$command] = $handler;
        }
        $this->handlers[spl_object_id($handler)] = $handler;

        return $this;
    }

    /**
     * Register multiple handlers at once.
     *
     * @param iterable<HandlerInterface> $handlers
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
     * Check if a command has a registered handler.
     *
     * @param string $command The command name
     */
    public function has(string $command): bool
    {
        return isset($this->commandMap[$command]);
    }

    /**
     * Get a handler by command name.
     *
     * @param string $command The command name (or alias)
     * @return HandlerInterface|null The handler or null if not found
     */
    public function get(string $command): ?HandlerInterface
    {
        return $this->commandMap[$command] ?? null;
    }

    /**
     * Execute a command using the appropriate handler.
     *
     * @param ParsedCommand $command The parsed command
     * @param HandlerContext $context The handler context
     * @return HandlerResult|null The result or null if no handler found
     */
    public function execute(ParsedCommand $command, HandlerContext $context): ?HandlerResult
    {
        $handler = $this->get($command->command);

        if ($handler === null) {
            return null;
        }

        return $handler->handle($command, $context);
    }

    /**
     * Get list of all primary command names.
     *
     * @return array<string>
     */
    public function getCommandList(): array
    {
        $commands = [];
        foreach ($this->handlers as $handler) {
            $cmds = $handler->getCommands();
            if (!empty($cmds)) {
                $commands[] = $cmds[0]; // Primary command only
            }
        }
        return $commands;
    }

    /**
     * Get all registered handlers.
     *
     * @return array<HandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Get command descriptions for help output.
     *
     * @return array<string, string> Primary command => description
     */
    public function getCommandDescriptions(): array
    {
        $descriptions = [];
        foreach ($this->handlers as $handler) {
            $cmds = $handler->getCommands();
            if (!empty($cmds)) {
                $primaryCommand = $cmds[0];
                $aliases = array_slice($cmds, 1);
                $description = $handler->getDescription();

                if (!empty($aliases)) {
                    $description .= ' (aliases: ' . implode(', ', $aliases) . ')';
                }

                $descriptions[$primaryCommand] = $description;
            }
        }
        ksort($descriptions);
        return $descriptions;
    }

    /**
     * Get usage examples for a command.
     *
     * @param string $command The command name
     * @return array<string>
     */
    public function getUsage(string $command): array
    {
        $handler = $this->get($command);
        return $handler?->getUsage() ?? [];
    }

    /**
     * Remove handlers for a command.
     *
     * @param string $command The command to remove
     * @return bool True if removed, false if not found
     */
    public function remove(string $command): bool
    {
        $handler = $this->commandMap[$command] ?? null;
        if ($handler === null) {
            return false;
        }

        // Remove all command mappings for this handler
        foreach ($handler->getCommands() as $cmd) {
            unset($this->commandMap[$cmd]);
        }

        // Remove from handlers array
        unset($this->handlers[spl_object_id($handler)]);

        return true;
    }

    /**
     * Clear all registered handlers.
     */
    public function clear(): void
    {
        $this->commandMap = [];
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
