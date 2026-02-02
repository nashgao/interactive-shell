<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell;

use NashGao\InteractiveShell\Command\AliasManager;
use NashGao\InteractiveShell\Command\BuiltInCommands;
use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Formatter\OutputFormat;
use NashGao\InteractiveShell\Formatter\OutputFormatter;
use NashGao\InteractiveShell\Formatter\OutputFormatterInterface;
use NashGao\InteractiveShell\Parser\ShellParser;
use NashGao\InteractiveShell\State\HistoryManager;
use NashGao\InteractiveShell\State\ShellState;
use NashGao\InteractiveShell\Transport\TransportInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interactive shell with pluggable transport and built-in commands.
 *
 * Provides a MySQL-like shell experience with:
 * - Command parsing with quote handling and options
 * - Multiple output formats (table, JSON, CSV, vertical \G)
 * - Command history with readline integration
 * - Command aliases
 * - Multi-line input support
 * - Session persistence
 */
final class Shell implements ShellInterface
{
    private readonly ShellParser $parser;
    private readonly OutputFormatterInterface $formatter;
    private readonly ShellState $state;
    private readonly HistoryManager $history;
    private readonly AliasManager $aliases;
    private readonly BuiltInCommands $builtIn;
    private OutputFormat $outputFormat = OutputFormat::Table;
    private bool $running = false;
    private string $prompt;

    /**
     * @param TransportInterface $transport The transport to use for remote commands
     * @param string $prompt Shell prompt string
     * @param array<string, string> $defaultAliases Default command aliases
     * @param string|null $historyFile Custom history file path (useful for testing)
     */
    public function __construct(
        private readonly TransportInterface $transport,
        string $prompt = 'shell> ',
        array $defaultAliases = [],
        ?string $historyFile = null,
    ) {
        $this->prompt = $prompt;
        $this->aliases = new AliasManager($defaultAliases);
        $this->parser = new ShellParser($this->aliases);
        $this->formatter = new OutputFormatter();
        $this->state = new ShellState();
        $this->history = new HistoryManager(historyFile: $historyFile);
        $this->builtIn = new BuiltInCommands(
            $this->state,
            $this->history,
            $this->aliases,
            $this->transport
        );
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->running = true;

        // Try to connect
        try {
            $this->transport->connect();
            $output->writeln("Connected to {$this->transport->getEndpoint()}");
        } catch (\Throwable $e) {
            $output->writeln("<error>Warning: {$e->getMessage()}</error>");
            $output->writeln('Running in offline mode - only built-in commands available');
        }

        // Display welcome message
        $output->writeln('Interactive Shell (type "help" for commands, "exit" to quit)');
        $output->writeln('');

        // Main REPL loop
        while ($this->running) {
            $currentPrompt = $this->state->isInMultiLine()
                ? $this->state->getContinuationPrompt()
                : $this->prompt;

            $line = $this->readLine($currentPrompt);

            if ($line === null) {
                // EOF (Ctrl+D)
                $output->writeln('');
                break;
            }

            // Process multi-line input
            $command = $this->state->processInput($line);
            if ($command === null) {
                continue; // Waiting for more input
            }

            // Skip empty commands
            $command = trim($command);
            if ($command === '') {
                continue;
            }

            // Add to history
            $this->history->add($command);

            // Execute command
            $exitCode = $this->executeCommand($command, $output);

            // Record command execution
            $this->state->recordCommand();

            // Check if we should exit
            if ($this->builtIn->shouldExit()) {
                $this->running = false;
            }
        }

        // Cleanup
        $this->state->saveSession();
        $this->history->save();
        $this->transport->disconnect();

        return 0;
    }

    public function executeCommand(string $command, OutputInterface $output): int
    {
        // Parse command
        $parsed = $this->parser->parse($command);

        if ($parsed->command === '') {
            return 0;
        }

        // Determine output format
        $format = $this->outputFormat;
        if ($parsed->hasVerticalTerminator) {
            $format = OutputFormat::Vertical;
        } elseif ($parsed->hasOption('format')) {
            $formatStr = $parsed->getOption('format');
            if (is_string($formatStr)) {
                $format = OutputFormat::fromString($formatStr);
            }
        }

        // Check for built-in command
        if ($this->builtIn->isBuiltIn($parsed->command)) {
            $result = $this->builtIn->execute($parsed, $output);
            return $result->getExitCode();
        }

        // Execute via transport
        if (!$this->transport->isConnected()) {
            $output->writeln('<error>Not connected to server. Use built-in commands or reconnect.</error>');
            return 1;
        }

        try {
            $result = $this->transport->send($parsed);
        } catch (\Throwable $e) {
            $result = CommandResult::fromException($e);
        }

        // Format and display output
        $formatted = $this->formatter->format($result, $format);
        $output->write($formatted);

        return $result->getExitCode();
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Set the shell prompt.
     */
    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    /**
     * Set the default output format.
     */
    public function setOutputFormat(OutputFormat $format): void
    {
        $this->outputFormat = $format;
    }

    /**
     * Get the transport.
     */
    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * Get the alias manager.
     */
    public function getAliases(): AliasManager
    {
        return $this->aliases;
    }

    /**
     * Get the history manager.
     */
    public function getHistory(): HistoryManager
    {
        return $this->history;
    }

    /**
     * Read a line of input from the user.
     */
    private function readLine(string $prompt): ?string
    {
        if (function_exists('readline')) {
            $line = readline($prompt);
            if ($line === false) {
                return null;
            }
            return $line;
        }

        // Fallback for non-readline environments
        echo $prompt;
        $line = fgets(STDIN);
        if ($line === false) {
            return null;
        }
        return rtrim($line, "\r\n");
    }
}
