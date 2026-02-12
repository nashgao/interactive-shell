<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\AbstractCommandHandler;

/**
 * Fixture: implements CommandHandlerInterface but has NO #[AsShellHandler] attribute.
 * Should be skipped by discovery.
 */
final class UnannotatedHandler extends AbstractCommandHandler
{
    public function getCommand(): string
    {
        return 'unannotated';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        return CommandResult::success('unannotated');
    }
}
