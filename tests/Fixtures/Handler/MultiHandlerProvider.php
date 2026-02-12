<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\AbstractCommandHandler;
use NashGao\InteractiveShell\Server\Handler\AsHandlerProvider;
use NashGao\InteractiveShell\Server\Handler\HandlerProviderInterface;

/**
 * Fixture: provider that yields multiple handlers.
 */
#[AsHandlerProvider]
final class MultiHandlerProvider implements HandlerProviderInterface
{
    public function getHandlers(): iterable
    {
        yield new class extends AbstractCommandHandler {
            public function getCommand(): string
            {
                return 'provider-cmd-1';
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success('from-provider-1');
            }
        };

        yield new class extends AbstractCommandHandler {
            public function getCommand(): string
            {
                return 'provider-cmd-2';
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success('from-provider-2');
            }
        };
    }
}
