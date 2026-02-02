<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;

/**
 * Health check handler that responds with "pong".
 */
final class PingHandler implements CommandHandlerInterface
{
    public function getCommand(): string
    {
        return 'ping';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        return CommandResult::success('pong', null, [
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ]);
    }

    public function getDescription(): string
    {
        return 'Health check - responds with "pong"';
    }

    public function getUsage(): array
    {
        return ['ping'];
    }
}
