<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;

/**
 * Handler with configurable delay - for testing timeouts and latency.
 *
 * This handler is useful for:
 * - Testing timeout behavior in transports
 * - Simulating slow network or server responses
 * - Testing progress indicators or loading states
 * - Verifying cancellation/interruption handling
 *
 * Example usage:
 * ```php
 * $server = new TestServer();
 * $server->register(new DelayHandler());
 *
 * // Default 1 second delay
 * $result = $server->dispatch(new ParsedCommand('delay', [], [], 'delay', false));
 * // Waits 1 second, then returns success
 *
 * // Custom delay (0.5 seconds)
 * $result = $server->dispatch(new ParsedCommand('delay', ['0.5'], [], 'delay 0.5', false));
 * // Waits 0.5 seconds
 * // $result->data === 'Delayed 0.5s'
 *
 * // Test timeout handling
 * $transport = new UnixSocketTransport($socketPath, timeout: 0.1);
 * $shell->executeCommand('delay 1');  // Should timeout
 * ```
 *
 * Note: Be mindful of delay values in tests - use small values (0.01-0.1s)
 * to keep test execution fast.
 */
final class DelayHandler implements CommandHandlerInterface
{
    public function getCommand(): string
    {
        return 'delay';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $seconds = (float) ($command->arguments[0] ?? 1);

        // Ensure reasonable bounds (0 to 60 seconds)
        $seconds = max(0.0, min(60.0, $seconds));

        if ($seconds > 0) {
            usleep((int) ($seconds * 1_000_000));
        }

        return CommandResult::success("Delayed {$seconds}s", "Completed after {$seconds} second(s)");
    }

    public function getDescription(): string
    {
        return 'Delay response by specified seconds (for timeout testing)';
    }

    /**
     * @return array<string>
     */
    public function getUsage(): array
    {
        return [
            'delay [seconds]',
            'delay        # Delays 1 second (default)',
            'delay 0.5    # Delays 500ms',
            'delay 2.5    # Delays 2.5 seconds',
        ];
    }
}
