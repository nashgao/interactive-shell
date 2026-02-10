<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler;

/**
 * Base class for command handlers that provides sensible defaults.
 *
 * Handler authors only need to implement getCommand() and handle().
 *
 * Usage:
 *   #[AsShellHandler]
 *   class MyHandler extends AbstractCommandHandler {
 *       public function getCommand(): string { return 'my-cmd'; }
 *       public function handle(ParsedCommand $cmd, ContextInterface $ctx): CommandResult {
 *           return CommandResult::success('done');
 *       }
 *   }
 */
abstract class AbstractCommandHandler implements CommandHandlerInterface
{
    public function getDescription(): string
    {
        return '';
    }

    /** @return array<string> */
    public function getUsage(): array
    {
        return [];
    }
}
