<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\StreamingHandler;

use NashGao\InteractiveShell\Parser\ParsedCommand;

/**
 * Interface for streaming shell command handlers.
 *
 * Each handler processes one or more commands in a streaming shell context.
 * Unlike server-side handlers, streaming handlers support:
 * - Multiple command aliases per handler
 * - State changes (pause, exit) via HandlerResult
 * - Rich context with filter, history, stats components
 */
interface HandlerInterface
{
    /**
     * Get the command names this handler responds to.
     *
     * The first command is the primary command, others are aliases.
     *
     * @return array<string>
     */
    public function getCommands(): array;

    /**
     * Handle the command.
     *
     * @param ParsedCommand $command The parsed command with arguments and options
     * @param HandlerContext $context The handler context with shell components
     * @return HandlerResult The result with any state changes
     */
    public function handle(ParsedCommand $command, HandlerContext $context): HandlerResult;

    /**
     * Get a short description of this command.
     *
     * Used in help output.
     *
     * @return string Human-readable description
     */
    public function getDescription(): string;

    /**
     * Get usage examples for this command.
     *
     * @return array<string> List of example usage strings
     */
    public function getUsage(): array;
}
