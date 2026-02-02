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
    private string $prompt;
    private int $messageCount = 0;

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
        $this->running = true;

        // Connect
        try {
            $this->transport->connect();
            $output->writeln("Connected to {$this->transport->getEndpoint()}");
        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to connect: {$e->getMessage()}</error>");
            return 1;
        }

        // Check for Swoole
        if (extension_loaded('swoole')) {
            return $this->runWithSwoole($input, $output);
        }

        // Fallback to polling mode
        return $this->runPolling($input, $output);
    }

    /**
     * Run with Swoole coroutines for true async I/O.
     *
     * Note: This method requires ext-swoole. The Swoole classes and functions
     * are only available when the extension is loaded.
     */
    private function runWithSwoole(InputInterface $input, OutputInterface $output): int
    {
        // Prevent unused parameter warning
        unset($input);

        $output->writeln('Streaming Shell (Swoole mode)');
        $output->writeln('Commands: filter <pattern>, pause, resume, clear, exit');
        $output->writeln('');

        // Start streaming
        $this->transport->startStreaming();

        // Swoole coroutine runtime - only available when ext-swoole is loaded
        // @phpstan-ignore-next-line (Swoole function only available when ext-swoole is loaded)
        \Swoole\Coroutine\run(function () use ($output): void {
            $channel = new \Swoole\Coroutine\Channel($this->channelBufferSize);

            // Coroutine 1: Message receiver
            go(function () use ($channel): void {
                while ($this->running) {
                    $message = $this->transport->receive(0.1);
                    if ($message !== null && !$this->paused) {
                        $channel->push($message);
                    }
                    \Swoole\Coroutine::sleep(0.01);
                }
            });

            // Coroutine 2: Message display
            go(function () use ($channel, $output): void {
                while ($this->running) {
                    $message = $channel->pop(0.1);
                    if ($message instanceof Message) {
                        if ($this->filter->matches($message)) {
                            $formatted = $this->messageFormatter->format($message);
                            $output->writeln($formatted);
                            ++$this->messageCount;
                        }
                    }
                }
            });

            // Coroutine 3: Input handler
            go(function () use ($output): void {
                while ($this->running) {
                    $line = $this->readLineNonBlocking($this->prompt);
                    if ($line !== null) {
                        $this->handleInput($line, $output);
                    }
                    \Swoole\Coroutine::sleep(0.01);
                }
            });

            // Wait for exit
            while ($this->running) {
                \Swoole\Coroutine::sleep(0.1);
            }
        });

        $this->cleanup($output);
        return 0;
    }

    /**
     * Run in polling mode (without Swoole).
     */
    private function runPolling(InputInterface $input, OutputInterface $output): int
    {
        // Prevent unused parameter warning
        unset($input);

        $output->writeln('Streaming Shell (polling mode - install Swoole for better performance)');
        $output->writeln('Commands: filter <pattern>, pause, resume, clear, exit');
        $output->writeln('');

        // Start streaming
        $this->transport->startStreaming();

        // Set non-blocking stdin
        stream_set_blocking(STDIN, false);

        while ($this->running) {
            // Check for messages
            if (!$this->paused) {
                $message = $this->transport->receive(0);
                if ($message !== null && $this->filter->matches($message)) {
                    $formatted = $this->messageFormatter->format($message);
                    $output->writeln($formatted);
                    ++$this->messageCount;
                }
            }

            // Check for input
            $line = fgets(STDIN);
            if ($line !== false) {
                $line = rtrim($line, "\r\n");
                $this->handleInput($line, $output);
            }

            // Small delay to prevent CPU spinning
            usleep(10000); // 10ms
        }

        // Restore blocking
        stream_set_blocking(STDIN, true);

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
                $this->paused = true;
                $output->writeln('<info>Streaming paused</info>');
                return;

            case 'resume':
                $this->paused = false;
                $output->writeln('<info>Streaming resumed</info>');
                return;

            case 'stats':
                $output->writeln(sprintf('Messages received: %d', $this->messageCount));
                $output->writeln(sprintf('Filter: %s', $this->filter->toString()));
                $output->writeln(sprintf('Paused: %s', $this->paused ? 'Yes' : 'No'));
                return;

            case 'exit':
            case 'quit':
                $this->running = false;
                return;
        }

        // Check built-in commands
        if ($this->builtIn->isBuiltIn($parsed->command)) {
            $result = $this->builtIn->execute($parsed, $output);
            if ($this->builtIn->shouldExit()) {
                $this->running = false;
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
        return $this->running;
    }

    public function stop(): void
    {
        $this->running = false;
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
        static $buffer = '';

        // This requires a TTY for proper operation
        // In Swoole, we'd use Swoole\Coroutine\System::fgets() or similar

        $read = [STDIN];
        $write = $except = [];
        $changed = @stream_select($read, $write, $except, 0, 10000);

        if ($changed > 0) {
            $char = fgetc(STDIN);
            if ($char === false || $char === "\n") {
                $line = $buffer;
                $buffer = '';
                return $line;
            }
            $buffer .= $char;
        }

        return null;
    }
}
