<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;

/**
 * Interface for server-side command handlers.
 *
 * Each handler processes a specific command and returns a result.
 * Handlers must be stateless and coroutine-safe.
 */
interface CommandHandlerInterface
{
    /**
     * Get the command name this handler responds to.
     *
     * @return string The command name (e.g., 'ping', 'config')
     */
    public function getCommand(): string;

    /**
     * Handle the command and return a result.
     *
     * @param ParsedCommand $command The parsed command with arguments/options
     * @param ContextInterface $context The framework context
     * @return CommandResult The execution result
     */
    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult;

    /**
     * Get a short description of what this command does.
     *
     * @return string Human-readable description for help output
     */
    public function getDescription(): string;

    /**
     * Get usage examples for this command.
     *
     * @return array<string> List of example usage strings
     */
    public function getUsage(): array;
}
