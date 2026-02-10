<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\AbstractCommandHandler;
use NashGao\InteractiveShell\Server\Handler\AsShellHandler;

/**
 * Fixture: annotated handler with command and description overrides.
 */
#[AsShellHandler(command: 'custom-cmd', description: 'Custom description')]
final class AnnotatedOverrideHandler extends AbstractCommandHandler
{
    public function getCommand(): string
    {
        return 'original-cmd';
    }

    public function getDescription(): string
    {
        return 'Original description';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        return CommandResult::success('override');
    }
}
