<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;

/**
 * Help handler that lists available commands and their descriptions.
 */
final class HelpHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CommandRegistry $registry
    ) {}

    public function getCommand(): string
    {
        return 'help';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $specificCommand = $command->getArgument(0);

        if ($specificCommand !== null && $specificCommand !== '') {
            return $this->showCommandHelp(is_scalar($specificCommand) ? (string) $specificCommand : '');
        }

        return $this->showAllCommands();
    }

    private function showAllCommands(): CommandResult
    {
        $commands = [];
        foreach ($this->registry->getHandlers() as $name => $handler) {
            $commands[] = [
                'command' => $name,
                'description' => $handler->getDescription(),
            ];
        }

        usort($commands, fn(array $a, array $b) => strcmp($a['command'], $b['command']));

        return CommandResult::success($commands, 'Available commands:');
    }

    private function showCommandHelp(string $commandName): CommandResult
    {
        $handler = $this->registry->get($commandName);

        if ($handler === null) {
            return CommandResult::failure(
                sprintf("Unknown command: '%s'", $commandName),
                ['available' => $this->registry->getCommandList()]
            );
        }

        return CommandResult::success([
            'command' => $handler->getCommand(),
            'description' => $handler->getDescription(),
            'usage' => $handler->getUsage(),
        ]);
    }

    public function getDescription(): string
    {
        return 'Show available commands or help for a specific command';
    }

    public function getUsage(): array
    {
        return [
            'help            - List all commands',
            'help <command>  - Show help for specific command',
        ];
    }
}
