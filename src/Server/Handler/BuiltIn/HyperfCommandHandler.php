<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Handler that executes Hyperf console commands.
 *
 * This handler acts as a fallback to execute any registered Hyperf console
 * command when no built-in shell handler matches the command name.
 */
final class HyperfCommandHandler implements CommandHandlerInterface
{
    use ConsoleApplicationAwareTrait;

    public function getCommand(): string
    {
        return '*'; // Fallback handler - matches any command
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $app = $this->getConsoleApplication($context);

        if ($app === null) {
            return CommandResult::failure('Console application not available');
        }

        if (!$app->has($command->command)) {
            return CommandResult::failure(
                sprintf("Unknown command: '%s'. Type 'help' or 'commands' for available commands.", $command->command)
            );
        }

        $consoleCommand = $app->find($command->command);
        $input = $this->buildInput($command, $consoleCommand);
        $output = new BufferedOutput();

        try {
            $exitCode = $consoleCommand->run($input, $output);
            $content = $output->fetch();

            return $exitCode === Command::SUCCESS
                ? CommandResult::success($content, 'Command executed successfully')
                : CommandResult::failure($content ?: 'Command failed with exit code: ' . $exitCode);
        } catch (\Throwable $e) {
            return CommandResult::failure($e->getMessage());
        }
    }

    public function getDescription(): string
    {
        return 'Execute Hyperf console commands';
    }

    public function getUsage(): array
    {
        return [
            '<command>              - Execute a Hyperf console command',
            'migrate               - Run database migrations',
            'migrate --seed        - Run migrations with seeding',
            'gen:model users       - Generate a model for the users table',
            'commands              - List all available commands',
        ];
    }

    /**
     * Build Symfony Console ArrayInput from ParsedCommand.
     *
     * Maps positional arguments to named parameters based on command definition.
     * Handles array arguments by collecting remaining arguments.
     */
    private function buildInput(ParsedCommand $parsed, Command $consoleCommand): ArrayInput
    {
        $definition = $consoleCommand->getDefinition();
        $params = [];

        // Map positional arguments to their named parameters
        $argNames = array_keys($definition->getArguments());
        $argIndex = 0;

        foreach ($argNames as $argName) {
            $argDef = $definition->getArgument($argName);

            if ($argDef->isArray()) {
                // Collect remaining arguments for array argument
                $params[$argName] = array_slice($parsed->arguments, $argIndex);
                break;
            }

            if (isset($parsed->arguments[$argIndex])) {
                $params[$argName] = $parsed->arguments[$argIndex];
                $argIndex++;
            }
        }

        // Map options (--key=value or --flag)
        foreach ($parsed->options as $key => $value) {
            $optionKey = strlen($key) === 1 ? "-{$key}" : "--{$key}";
            $params[$optionKey] = $value;
        }

        return new ArrayInput($params);
    }
}
