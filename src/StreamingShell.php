<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell;

use NashGao\InteractiveShell\Command\AliasManager;
use NashGao\InteractiveShell\Command\BuiltInCommands;
use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Formatter\OutputFormat;
use NashGao\InteractiveShell\Formatter\OutputFormatter;
use NashGao\InteractiveShell\Message\FilterExpression;
use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Message\MessageFormatter;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Parser\ShellParser;
use NashGao\InteractiveShell\State\HistoryManager;
use NashGao\InteractiveShell\State\ShellState;
use NashGao\InteractiveShell\Transport\StreamingTransportInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interactive shell with bidirectional streaming support.
 *
 * Designed for real-time message streaming scenarios like MQTT debug shells
 * where messages arrive asynchronously while the user can still input commands.
 *
 * Features:
 * - Bidirectional communication (send commands, receive messages)
 * - Client-side message filtering
 * - Pause/resume streaming
 * - Message formatting with timestamps and colors
 *
 * Note: For full async support, this requires Swoole coroutines.
 * Without Swoole, it uses a simpler polling-based approach.
 */
final class StreamingShell implements ShellInterface
{
    private readonly ShellParser $parser;
    private readonly OutputFormatter $outputFormatter;
    private readonly MessageFormatter $messageFormatter;
    private readonly ShellState $state;
    private readonly HistoryManager $history;
    private readonly AliasManager $aliases;
    private readonly BuiltInCommands $builtIn;
    private FilterExpression $filter;
    private bool $running = false;
    private bool $paused = false;
    private ?\Swoole\Atomic $runningAtomic = null;
    private ?\Swoole\Atomic $pausedAtomic = null;
    private string $prompt;
    private int $messageCount = 0;
    private string $inputBuffer = '';

    /**
     * @param StreamingTransportInterface $transport Streaming transport
     * @param string $prompt Shell prompt
     * @param array<string, string> $defaultAliases Default aliases
     * @param int $channelBufferSize Swoole channel buffer size for message queue
     */
    public function __construct(
        private readonly StreamingTransportInterface $transport,
        string $prompt = 'stream> ',
        array $defaultAliases = [],
        private readonly int $channelBufferSize = 100,
    ) {
        $this->prompt = $prompt;
        $this->aliases = new AliasManager($defaultAliases);
        $this->parser = new ShellParser($this->aliases);
        $this->outputFormatter = new OutputFormatter();
        $this->messageFormatter = new MessageFormatter();
        $this->state = new ShellState();
        $this->history = new HistoryManager();
        $this->filter = new FilterExpression();
        $this->builtIn = new BuiltInCommands(
            $this->state,
            $this->history,
            $this->aliases,
            $this->transport
        );
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->setRunning(true);

        // Connect
        try {
            $this->transport->connect();
            $output->writeln("Connected to {$this->transport->getEndpoint()}");
        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to connect: {$e->getMessage()}</error>");
            return 1;
        }

        return $this->runWithSwoole($input, $output);
    }

    /**
     * Run with Swoole coroutines for true async I/O.
     *
     * Note: This method requires ext-swoole. The Swoole classes and functions
     * are only available when the extension is loaded.
     */
    private function runWithSwoole(InputInterface $_input, OutputInterface $output): int
    {
        $output->writeln('Streaming Shell (Swoole mode)');
        $output->writeln('Commands: filter <pattern>, pause, resume, clear, exit');
        $output->writeln('');

        // Start streaming
        try {
            $this->transport->startStreaming();
        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to start streaming: {$e->getMessage()}</error>");
            $this->transport->disconnect();
            return 1;
        }

        // Initialize atomic flags for thread-safe state across coroutines
        $this->runningAtomic = new \Swoole\Atomic(1);
        $this->pausedAtomic = new \Swoole\Atomic(0);

        \Swoole\Coroutine\run(function () use ($output): void {
            $channel = new \Swoole\Coroutine\Channel($this->channelBufferSize);

            // Coroutine 1: Message receiver — always push (never drop while paused)
            go(function () use ($channel, $output): void {
                try {
                    while ($this->isStillRunning()) {
                        $message = $this->transport->receive(0.1);
                        if ($message !== null) {
                            $channel->push($message);
                        }
                        \Swoole\Coroutine::sleep(0.01);
                    }
                } catch (\Throwable $e) {
                    $this->setRunning(false);
                    $output->writeln("<error>Receiver error: {$e->getMessage()}</error>");
                }
            });

            // Coroutine 2: Message display — skip pop entirely when paused
            go(function () use ($channel, $output): void {
                try {
                    while ($this->isStillRunning()) {
                        if ($this->isPaused()) {
                            \Swoole\Coroutine::sleep(0.1);
                            continue;
                        }
                        $message = $channel->pop(0.1);
                        if ($message instanceof Message && $this->filter->matches($message)) {
                            $formatted = $this->messageFormatter->format($message);
                            $output->writeln($formatted);
                            ++$this->messageCount;
                        }
                    }
                } catch (\Throwable $e) {
                    $this->setRunning(false);
                    $output->writeln("<error>Display error: {$e->getMessage()}</error>");
                }
            });

            // Coroutine 3: Input handler
            go(function () use ($output): void {
                try {
                    while ($this->isStillRunning()) {
                        $line = $this->readLineNonBlocking($this->prompt);
                        if ($line !== null) {
                            $this->handleInput($line, $output);
                        }
                        \Swoole\Coroutine::sleep(0.01);
                    }
                } catch (\Throwable $e) {
                    $this->setRunning(false);
                    $output->writeln("<error>Input error: {$e->getMessage()}</error>");
                }
            });

            // Wait for exit
            try {
                while ($this->isStillRunning()) {
                    \Swoole\Coroutine::sleep(0.1);
                }
            } catch (\Throwable $e) {
                $this->setRunning(false);
                $output->writeln("<error>Shell error: {$e->getMessage()}</error>");
            }
        });

        $this->runningAtomic = null;
        $this->pausedAtomic = null;
        $this->cleanup($output);
        return 0;
    }

    /**
     * Handle user input.
     */
    private function handleInput(string $line, OutputInterface $output): void
    {
        $line = trim($line);
        if ($line === '') {
            return;
        }

        $this->history->add($line);

        // Parse command
        $parsed = $this->parser->parse($line);
        $command = strtolower($parsed->command);

        // Handle streaming-specific commands
        switch ($command) {
            case 'filter':
                $this->handleFilter($parsed, $output);
                return;

            case 'pause':
                $this->setPaused(true);
                $output->writeln('<info>Streaming paused</info>');
                return;

            case 'resume':
                $this->setPaused(false);
                $output->writeln('<info>Streaming resumed</info>');
                return;

            case 'stats':
                $output->writeln(sprintf('Messages received: %d', $this->messageCount));
                $output->writeln(sprintf('Filter: %s', $this->filter->toString()));
                $output->writeln(sprintf('Paused: %s', $this->isPaused() ? 'Yes' : 'No'));
                return;

            case 'exit':
            case 'quit':
                $this->setRunning(false);
                return;
        }

        // Check built-in commands (use lowercased $command to match COMMAND_MAP keys)
        if ($this->builtIn->isBuiltIn($command)) {
            $this->builtIn->execute($parsed, $output);
            if ($this->builtIn->shouldExit()) {
                $this->setRunning(false);
            }
            return;
        }

        // Send command to server (async)
        $this->transport->sendAsync($parsed);
        $output->writeln("<info>Command sent: {$parsed->command}</info>");
    }

    /**
     * Handle filter command.
     */
    private function handleFilter(ParsedCommand $parsed, OutputInterface $output): void
    {
        $arg = $parsed->getArgument(0);

        if ($arg === null || $arg === 'show') {
            $output->writeln('Current filter: ' . $this->filter->toString());
            return;
        }

        if ($arg === 'clear' || $arg === 'none') {
            $this->filter->clear();
            $output->writeln('<info>Filter cleared - showing all messages</info>');
            return;
        }

        // Parse filter expression
        $filterString = implode(' ', $parsed->arguments);
        $this->filter = FilterExpression::parse($filterString);
        $output->writeln('<info>Filter set: ' . $this->filter->toString() . '</info>');
    }

    /**
     * Cleanup on exit.
     */
    private function cleanup(OutputInterface $output): void
    {
        $this->transport->stopStreaming();
        $this->transport->disconnect();
        $this->state->saveSession();
        $this->history->save();

        $output->writeln('');
        $output->writeln(sprintf('Session ended. Total messages: %d', $this->messageCount));
    }

    public function executeCommand(string $command, OutputInterface $output): int
    {
        $this->handleInput($command, $output);
        return 0;
    }

    public function isRunning(): bool
    {
        return $this->isStillRunning();
    }

    public function stop(): void
    {
        $this->setRunning(false);
    }

    /**
     * Thread-safe running check (uses Swoole\Atomic when in coroutine mode).
     */
    private function isStillRunning(): bool
    {
        if ($this->runningAtomic !== null) {
            return $this->runningAtomic->get() === 1;
        }
        return $this->running;
    }

    /**
     * Thread-safe running setter (uses Swoole\Atomic when in coroutine mode).
     */
    private function setRunning(bool $running): void
    {
        $this->running = $running;
        $this->runningAtomic?->set($running ? 1 : 0);
    }

    /**
     * Thread-safe pause check (uses Swoole\Atomic when in coroutine mode).
     */
    private function isPaused(): bool
    {
        if ($this->pausedAtomic !== null) {
            return $this->pausedAtomic->get() === 1;
        }
        return $this->paused;
    }

    /**
     * Thread-safe pause setter (uses Swoole\Atomic when in coroutine mode).
     */
    private function setPaused(bool $paused): void
    {
        $this->paused = $paused;
        $this->pausedAtomic?->set($paused ? 1 : 0);
    }

    /**
     * Set the message filter.
     */
    public function setFilter(FilterExpression $filter): void
    {
        $this->filter = $filter;
    }

    /**
     * Get current message count.
     */
    public function getMessageCount(): int
    {
        return $this->messageCount;
    }

    /**
     * Get the output formatter for command results.
     */
    public function getOutputFormatter(): OutputFormatter
    {
        return $this->outputFormatter;
    }

    /**
     * Non-blocking readline for Swoole.
     */
    private function readLineNonBlocking(string $prompt): ?string
    {
        $read = [STDIN];
        $write = $except = [];
        $changed = @stream_select($read, $write, $except, 0, 10000);

        if ($changed > 0) {
            $char = fgetc(STDIN);
            if ($char === false || $char === "\n") {
                $line = $this->inputBuffer;
                $this->inputBuffer = '';
                return $line;
            }
            $this->inputBuffer .= $char;
        }

        return null;
    }
}
