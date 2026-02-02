<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use Symfony\Component\Console\Application;

/**
 * Handler for listing all available Hyperf console commands.
 *
 * Discovers and displays all commands registered in the Hyperf application,
 * making it easy to see what's available from the interactive shell.
 */
final class CommandListHandler implements CommandHandlerInterface
{
    use ConsoleApplicationAwareTrait;

    public function getCommand(): string
    {
        return 'commands';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $app = $this->getConsoleApplication($context);

        if ($app === null) {
            return CommandResult::failure('Console application not available');
        }

        $filter = $command->getArgument(0);
        $commands = [];

        foreach ($app->all() as $name => $cmd) {
            // Skip hidden commands and aliases
            if ($cmd->isHidden() || $name !== $cmd->getName()) {
                continue;
            }

            // Apply filter if provided
            if (is_scalar($filter) && $filter !== '' && !str_contains($name, (string) $filter)) {
                continue;
            }

            $commands[] = [
                'name' => $cmd->getName(),
                'description' => $cmd->getDescription(),
            ];
        }

        // Sort alphabetically by name
        usort($commands, fn(array $a, array $b) => strcmp($a['name'], $b['name']));

        if (empty($commands)) {
            return CommandResult::success([], 'No commands match the filter criteria');
        }

        return CommandResult::success($commands, sprintf('Found %d command(s)', count($commands)));
    }

    public function getDescription(): string
    {
        return 'List all available Hyperf console commands';
    }

    public function getUsage(): array
    {
        return [
            'commands            - List all available commands',
            'commands migrate    - Filter commands containing "migrate"',
            'commands gen        - Filter commands containing "gen"',
        ];
    }
}
