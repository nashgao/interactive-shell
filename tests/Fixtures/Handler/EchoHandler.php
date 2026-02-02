<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;

/**
 * Echo handler that returns command arguments as response.
 *
 * This handler is useful for:
 * - Testing basic command flow and argument parsing
 * - Verifying the Shell-Transport-Server pipeline works
 * - Checking that arguments are correctly passed through layers
 *
 * Example usage:
 * ```php
 * $server = new TestServer();
 * $server->register(new EchoHandler());
 *
 * // Basic echo
 * $result = $server->dispatch(new ParsedCommand('echo', ['hello'], [], 'echo hello', false));
 * // $result->success === true
 * // $result->data === 'hello'
 *
 * // Multiple arguments
 * $result = $server->dispatch(new ParsedCommand('echo', ['hello', 'world'], [], 'echo hello world', false));
 * // $result->data === 'hello world'
 *
 * // No arguments
 * $result = $server->dispatch(new ParsedCommand('echo', [], [], 'echo', false));
 * // $result->data === ''
 * ```
 */
final class EchoHandler implements CommandHandlerInterface
{
    public function getCommand(): string
    {
        return 'echo';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $message = implode(' ', $command->arguments);
        return CommandResult::success($message);
    }

    public function getDescription(): string
    {
        return 'Echo back all arguments as a single string';
    }

    /**
     * @return array<string>
     */
    public function getUsage(): array
    {
        return [
            'echo <message...>',
            'echo hello world  # Returns: "hello world"',
        ];
    }
}
