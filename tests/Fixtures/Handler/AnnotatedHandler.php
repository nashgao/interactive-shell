<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\AbstractCommandHandler;
use NashGao\InteractiveShell\Server\Handler\AsShellHandler;

/**
 * Fixture: annotated handler with no attribute overrides.
 */
#[AsShellHandler]
final class AnnotatedHandler extends AbstractCommandHandler
{
    public function getCommand(): string
    {
        return 'annotated';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        return CommandResult::success('annotated');
    }
}
