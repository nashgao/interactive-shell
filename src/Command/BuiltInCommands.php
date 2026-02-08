<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Command;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\State\HistoryManager;
use NashGao\InteractiveShell\State\ShellState;
use NashGao\InteractiveShell\Transport\TransportInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Built-in shell commands that don't require server communication.
 */
final class BuiltInCommands
{
    /**
     * @var array<string, string>
     */
    private const COMMAND_MAP = [
        'help' => 'handleHelp',
        'exit' => 'handleExit',
        'quit' => 'handleExit',
        'status' => 'handleStatus',
        'clear' => 'handleClear',
        'history' => 'handleHistory',
        'alias' => 'handleAlias',
        'unalias' => 'handleUnalias',
        'reconnect' => 'handleReconnect',
        'ping' => 'handlePing',
    ];

    private bool $shouldExit = false;

    public function __construct(
        private readonly ShellState $state,
        private readonly HistoryManager $history,
        private readonly AliasManager $aliases,
        private readonly ?TransportInterface $transport = null,
    ) {}

    public function isBuiltIn(string $command): bool
    {
        return isset(self::COMMAND_MAP[$command]);
    }

    public function shouldExit(): bool
    {
        return $this->shouldExit;
    }

    public function execute(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        $command = $parsed->command;
        $method = self::COMMAND_MAP[$command] ?? null;

        if ($method === null) {
            return CommandResult::failure("Unknown built-in command: {$command}");
        }

        return $this->{$method}($parsed, $output);
    }

    private function handleHelp(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        $help = <<<'HELP'
Interactive Shell - Available Commands

Built-in commands:
  help              Show this help message
  exit, quit        Exit the shell
  status            Show connection status
  clear             Clear the screen
  history           Show command history
  alias [name=cmd]  Show or set aliases
  unalias <name>    Remove an alias
  reconnect         Reconnect to the server
  ping              Check if the server is reachable

Navigation:
  Use up/down arrows to navigate command history
  Use \G at end of command for vertical output (MySQL-style)
  Use \ at end of line for multi-line input

Output formats:
  --format=table    ASCII table format (default)
  --format=json     JSON format
  --format=csv      CSV format
  --format=vertical MySQL \G style format

HELP;
        $output->writeln($help);
        return CommandResult::success(message: 'Help displayed');
    }

    private function handleExit(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        $this->shouldExit = true;
        $this->state->saveSession();
        $this->history->save();
        $output->writeln('Goodbye!');
        return CommandResult::success(message: 'Exiting');
    }

    private function handleStatus(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        // Prevent unused parameter warning
        unset($parsed);

        $metrics = $this->state->getSessionMetrics();

        $sessionStart = $metrics['session_start'] ?? '';
        $sessionDuration = $metrics['session_duration'] ?? '';
        $commandsExecuted = $metrics['commands_executed'] ?? 0;

        $output->writeln('Shell Status:');
        $output->writeln(sprintf('  Session started: %s', is_string($sessionStart) ? $sessionStart : ''));
        $output->writeln(sprintf('  Session duration: %s', is_string($sessionDuration) ? $sessionDuration : ''));
        $output->writeln(sprintf('  Commands executed: %d', is_int($commandsExecuted) ? $commandsExecuted : 0));

        if ($this->transport !== null) {
            $connected = $this->transport->isConnected();
            $output->writeln(sprintf('  Server: %s', $this->transport->getEndpoint()));
            $output->writeln(sprintf('  Connected: %s', $connected ? 'Yes' : 'No'));
        }

        return CommandResult::success($metrics);
    }

    private function handleClear(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        $output->write("\033[2J\033[H");
        return CommandResult::success(message: 'Screen cleared');
    }

    private function handleHistory(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        $history = $this->history->getHistory();

        if (empty($history)) {
            $output->writeln('No command history');
            return CommandResult::success([]);
        }

        $output->writeln('Command History:');
        foreach ($history as $index => $command) {
            $output->writeln(sprintf('  %4d  %s', $index + 1, $command));
        }

        return CommandResult::success($history);
    }

    private function handleAlias(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        $rawArg = $parsed->getArgument(0);

        // No argument - show all aliases
        if ($rawArg === null) {
            $aliases = $this->aliases->getAliases();

            if (empty($aliases)) {
                $output->writeln('No aliases defined');
                return CommandResult::success([]);
            }

            $output->writeln('Aliases:');
            foreach ($aliases as $name => $expansion) {
                $output->writeln(sprintf('  %s = %s', $name, $expansion));
            }

            return CommandResult::success($aliases);
        }

        $arg = is_scalar($rawArg) ? (string) $rawArg : '';

        // Set alias: alias name=command
        if (str_contains($arg, '=')) {
            [$name, $command] = explode('=', $arg, 2);
            $name = trim($name);
            $command = trim($command);

            try {
                $this->aliases->setAlias($name, $command);
                $output->writeln(sprintf('Alias set: %s = %s', $name, $command));
                return CommandResult::success(message: "Alias '{$name}' set");
            } catch (\InvalidArgumentException $e) {
                return CommandResult::failure($e->getMessage());
            }
        }

        // Show specific alias
        if ($this->aliases->hasAlias($arg)) {
            $aliases = $this->aliases->getAliases();
            $output->writeln(sprintf('%s = %s', $arg, $aliases[$arg]));
            return CommandResult::success([$arg => $aliases[$arg]]);
        }

        return CommandResult::failure("Alias not found: {$arg}");
    }

    private function handleUnalias(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        $rawName = $parsed->getArgument(0);

        if ($rawName === null) {
            return CommandResult::failure('Usage: unalias <name>');
        }

        $name = is_scalar($rawName) ? (string) $rawName : '';

        if ($this->aliases->removeAlias($name)) {
            $output->writeln(sprintf('Alias removed: %s', $name));
            return CommandResult::success(message: "Alias '{$name}' removed");
        }

        return CommandResult::failure("Alias not found: {$name}");
    }

    private function handleReconnect(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        if ($this->transport === null) {
            return CommandResult::failure('No transport configured');
        }

        $output->writeln('Reconnecting...');

        try {
            $this->transport->disconnect();
            $this->transport->connect();
            $output->writeln('Reconnected to ' . $this->transport->getEndpoint());
            return CommandResult::success(message: 'Reconnected');
        } catch (\Throwable $e) {
            return CommandResult::failure('Reconnect failed: ' . $e->getMessage());
        }
    }

    private function handlePing(ParsedCommand $parsed, OutputInterface $output): CommandResult
    {
        if ($this->transport === null) {
            return CommandResult::failure('No transport configured');
        }

        $reachable = $this->transport->ping();
        $endpoint = $this->transport->getEndpoint();

        if ($reachable) {
            $output->writeln("Server {$endpoint} is reachable");
            return CommandResult::success(message: 'Server is reachable');
        }

        $output->writeln("Server {$endpoint} is not reachable");
        return CommandResult::failure('Server is not reachable');
    }
}
