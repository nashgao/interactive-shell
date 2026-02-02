<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;

/**
 * Handler that always fails - for testing error handling paths.
 *
 * This handler is essential for testing:
 * - Error message propagation from server to client
 * - Shell error formatting and display
 * - Exit code handling for failed commands
 * - Error recovery flows
 *
 * Example usage:
 * ```php
 * $server = new TestServer();
 * $server->register(new ErrorHandler());
 *
 * // Default error message
 * $result = $server->dispatch(new ParsedCommand('fail', [], [], 'fail', false));
 * // $result->success === false
 * // $result->error === 'Intentional failure'
 *
 * // Custom error message
 * $result = $server->dispatch(new ParsedCommand('fail', ['Database connection lost'], [], 'fail Database connection lost', false));
 * // $result->error === 'Database connection lost'
 *
 * // Test with Shell
 * $shell = new Shell($transport, 'test> ');
 * $exitCode = $shell->executeCommand('fail Custom error', $output);
 * // $exitCode === 1
 * // $output contains formatted error
 * ```
 */
final class ErrorHandler implements CommandHandlerInterface
{
    public function getCommand(): string
    {
        return 'fail';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $message = !empty($command->arguments)
            ? implode(' ', $command->arguments)
            : 'Intentional failure';

        return CommandResult::failure($message);
    }

    public function getDescription(): string
    {
        return 'Always fails with an error (for testing error handling)';
    }

    /**
     * @return array<string>
     */
    public function getUsage(): array
    {
        return [
            'fail [error-message]',
            'fail                    # Uses default error message',
            'fail Connection refused # Uses custom error message',
        ];
    }
}
