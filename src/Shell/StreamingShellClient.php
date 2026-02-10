<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Shell;

use NashGao\InteractiveShell\Command\AliasManager;
use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Parser\ShellParser;
use NashGao\InteractiveShell\State\HistoryManager;
use NashGao\InteractiveShell\StreamingHandler\HandlerContext;
use NashGao\InteractiveShell\StreamingHandler\HandlerInterface;
use NashGao\InteractiveShell\StreamingHandler\HandlerResult;
use NashGao\InteractiveShell\Transport\StreamingTransportInterface;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base streaming shell client with Swoole coroutine execution.
 *
 * Provides the core shell infrastructure:
 * - Command parsing and routing
 * - Handler registration and execution
 * - Swoole coroutine-based async I/O
 * - Pause/resume state management
 * - Command history and aliases
 *
 * Subclasses implement protocol-specific:
 * - Component initialization (filter, formatter, history, stats)
 * - Handler registration
 * - Message processing pipeline
 * - Context creation
 */
abstract class StreamingShellClient
{
    protected readonly ShellParser $parser;

    protected readonly AliasManager $aliases;

    protected readonly HistoryManager $commandHistory;

    protected OutputInterface $output;

    /**
     * Registered command handlers.
     *
     * @var array<string, HandlerInterface>
     */
    protected array $handlers = [];

    protected bool $running = false;

    protected bool $paused = false;

    protected ?\Swoole\Atomic $pausedAtomic = null;

    protected ?\Swoole\Atomic $runningAtomic = null;

    protected string $prompt;

    protected int $channelBufferSize = 100;

    /**
     * @param array<string, string> $defaultAliases
     */
    public function __construct(
        protected readonly StreamingTransportInterface $transport,
        string $prompt = 'shell> ',
        array $defaultAliases = [],
    ) {
        $this->prompt = $prompt;

        // Initialize base components
        $mergedAliases = array_merge($this->getDefaultAliases(), $defaultAliases);
        $this->aliases = new AliasManager($mergedAliases);
        $this->parser = new ShellParser($this->aliases);
        $this->commandHistory = new HistoryManager();
    }

    /**
     * Run the streaming shell.
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        // Deferred from constructor so $output is available in subclass overrides
        $this->initialize();
        $this->registerHandlers();

        $this->setRunning(true);

        // Connect
        try {
            $this->transport->connect();
            $output->writeln("<info>Connected to {$this->transport->getEndpoint()}</info>");
        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to connect: {$e->getMessage()}</error>");
            return 1;
        }

        // Show welcome
        $this->printWelcome($output);

        return $this->runWithSwoole($input, $output);
    }

    /**
     * Check if shell is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Stop the shell.
     */
    public function stop(): void
    {
        $this->setRunning(false);
    }

    // ─── Abstract Methods (Protocol-specific) ────────────────────────────

    /**
     * Initialize protocol-specific components (filter, formatter, history, stats).
     */
    abstract protected function initialize(): void;

    /**
     * Register protocol-specific handlers.
     */
    abstract protected function registerHandlers(): void;

    /**
     * Create protocol-specific handler context.
     */
    abstract protected function createContext(OutputInterface $output, bool $verticalFormat): HandlerContext;

    /**
     * Process an incoming message through protocol-specific pipeline.
     */
    abstract protected function processMessage(Message $message, OutputInterface $output): void;

    /**
     * Get protocol-specific default aliases.
     *
     * @return array<string, string>
     */
    abstract protected function getDefaultAliases(): array;

    // ─── Optional Override Points ────────────────────────────────────────

    /**
     * Get welcome message lines.
     *
     * @return string[]
     */
    protected function getWelcomeLines(): array
    {
        return [
            '',
            '<info>Streaming Shell</info>',
            sprintf('Prompt: <comment>%s</comment>', $this->prompt),
            'Type <comment>help</comment> for available commands, <comment>exit</comment> to quit',
            '',
        ];
    }

    /**
     * Handle unknown command (default: send to server).
     */
    protected function handleUnknownCommand(ParsedCommand $parsed, OutputInterface $output): void
    {
        $this->transport->sendAsync($parsed);
        $output->writeln("<comment>Command sent to server: {$parsed->command}</comment>");
    }

    /**
     * Get the readline history file path.
     */
    protected function getReadlineHistoryFile(): string
    {
        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: sys_get_temp_dir());
        return $home . '/.shell_history';
    }

    /**
     * Print session end message.
     */
    protected function printSessionEnd(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('Session ended.');
    }

    // ─── Handler Registration ────────────────────────────────────────────

    /**
     * Register a handler for its commands.
     */
    protected function registerHandler(HandlerInterface $handler): void
    {
        foreach ($handler->getCommands() as $cmd) {
            $this->handlers[$cmd] = $handler;
        }
    }

    /**
     * Get registered handlers.
     *
     * @return array<string, HandlerInterface>
     */
    protected function getHandlers(): array
    {
        return $this->handlers;
    }

    // ─── Thread-safe State ─────────────────────────────────────────────

    /**
     * Thread-safe running check (uses Swoole\Atomic when in coroutine mode).
     */
    protected function isStillRunning(): bool
    {
        if ($this->runningAtomic !== null) {
            return $this->runningAtomic->get() === 1;
        }
        return $this->running;
    }

    /**
     * Thread-safe running setter (uses Swoole\Atomic when in coroutine mode).
     */
    protected function setRunning(bool $running): void
    {
        $this->running = $running;
        $this->runningAtomic?->set($running ? 1 : 0);
    }

    /**
     * Check if message display is paused (thread-safe in Swoole mode).
     */
    protected function isPaused(): bool
    {
        if ($this->pausedAtomic !== null) {
            return $this->pausedAtomic->get() === 1;
        }
        return $this->paused;
    }

    /**
     * Set pause state (thread-safe in Swoole mode).
     */
    protected function setPaused(bool $paused): void
    {
        if ($this->pausedAtomic !== null) {
            $this->pausedAtomic->set($paused ? 1 : 0);
        }
        $this->paused = $paused;
    }

    // ─── Execution Modes ─────────────────────────────────────────────────

    /**
     * Run with Swoole coroutines for true async I/O.
     */
    protected function runWithSwoole(InputInterface $_input, OutputInterface $output): int
    {
        $output->writeln('<comment>Running in Swoole mode</comment>');
        $output->writeln('');

        try {
            $this->transport->startStreaming();
        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to start streaming: {$e->getMessage()}</error>");
            $this->transport->disconnect();
            return 1;
        }

        // Initialize atomic flags for thread-safe state across coroutines
        $this->pausedAtomic = new \Swoole\Atomic($this->paused ? 1 : 0);
        $this->runningAtomic = new \Swoole\Atomic(1);

        // Set STDIN to non-blocking for coroutine input handling
        stream_set_blocking(STDIN, false);

        \Swoole\Coroutine\run(function () use ($output): void {
            $channel = new Channel($this->channelBufferSize);

            // Coroutine 1: Message receiver
            \Swoole\Coroutine\go(function () use ($channel, $output): void {
                try {
                    while ($this->isStillRunning()) {
                        $message = $this->transport->receive(0.1);
                        if ($message !== null) {
                            $channel->push($message);
                        }
                        Coroutine::sleep(0.01);
                    }
                } catch (\Throwable $e) {
                    $this->setRunning(false);
                    $output->writeln("<error>Receiver error: {$e->getMessage()}</error>");
                }
            });

            // Coroutine 2: Message processor and display
            \Swoole\Coroutine\go(function () use ($channel, $output): void {
                try {
                    while ($this->isStillRunning()) {
                        $message = $channel->pop(0.1);
                        if ($message instanceof Message) {
                            $this->processMessage($message, $output);
                        }
                    }
                } catch (\Throwable $e) {
                    $this->setRunning(false);
                    $output->writeln("<error>Display error: {$e->getMessage()}</error>");
                }
            });

            // Coroutine 3: Input handler
            \Swoole\Coroutine\go(function () use ($output): void {
                try {
                    // Show initial prompt
                    echo $this->prompt;
                    fflush(STDOUT);

                    while ($this->isStillRunning()) {
                        $line = $this->readLineNonBlocking();
                        if ($line !== null) {
                            $this->handleInput($line, $output);
                        }
                        Coroutine::sleep(0.01);
                    }
                } catch (\Throwable $e) {
                    $this->setRunning(false);
                    $output->writeln("<error>Input error: {$e->getMessage()}</error>");
                }
            });

            try {
                while ($this->isStillRunning()) {
                    Coroutine::sleep(0.1);
                }
            } catch (\Throwable $e) {
                $this->setRunning(false);
                $output->writeln("<error>Shell error: {$e->getMessage()}</error>");
            }
        });

        // Release atomics before cleanup
        $this->pausedAtomic = null;
        $this->runningAtomic = null;

        // Restore STDIN to blocking mode before cleanup
        stream_set_blocking(STDIN, true);
        $this->cleanup($output);
        return 0;
    }

    // ─── Input Handling ──────────────────────────────────────────────────

    /**
     * Handle user input.
     */
    protected function handleInput(string $line, OutputInterface $output): void
    {
        $line = trim($line);
        if ($line === '') {
            return;
        }

        $this->commandHistory->add($line);

        // Check for vertical format suffix (e.g., "history\G")
        $verticalFormat = false;
        if (str_ends_with($line, '\G')) {
            $verticalFormat = true;
            $line = rtrim(substr($line, 0, -2));
        }

        $parsed = $this->parser->parse($line);
        $command = strtolower($parsed->command);

        // Find handler
        if (isset($this->handlers[$command])) {
            $handler = $this->handlers[$command];
            if ($handler instanceof HandlerInterface) {
                $context = $this->createContext($output, $verticalFormat);
                $result = $handler->handle($parsed, $context);
                $this->handleResult($result);
            }

            return;
        }

        // Unknown command
        $this->handleUnknownCommand($parsed, $output);
    }

    /**
     * Handle result from a handler.
     */
    protected function handleResult(HandlerResult $result): void
    {
        if ($result->shouldExit) {
            $this->setRunning(false);
            return;
        }
        if ($result->pauseState !== null) {
            $this->setPaused($result->pauseState);
        }
    }

    // ─── Utilities ───────────────────────────────────────────────────────

    /**
     * Print welcome message.
     */
    protected function printWelcome(OutputInterface $output): void
    {
        foreach ($this->getWelcomeLines() as $line) {
            $output->writeln($line);
        }
    }

    /**
     * Cleanup on exit.
     */
    protected function cleanup(OutputInterface $output): void
    {
        $this->transport->stopStreaming();
        $this->transport->disconnect();
        $this->commandHistory->save();

        $this->printSessionEnd($output);
    }

    /**
     * Non-blocking readline with auto-pause.
     *
     * When input is detected, automatically pauses message display,
     * shows the prompt, reads the full line, then resumes display.
     * This prevents messages from scrolling away the prompt while typing.
     */
    protected function readLineNonBlocking(): ?string
    {
        $read = [STDIN];
        $write = $except = [];

        // Check if input is available (non-blocking)
        $changed = @stream_select($read, $write, $except, 0, 10000);

        if ($changed > 0 && !feof(STDIN)) {
            // Input detected - auto-pause to prevent message scroll
            $wasPaused = $this->isPaused();
            if (!$wasPaused) {
                $this->setPaused(true);
            }

            // Show prompt and read input
            echo $this->prompt;
            fflush(STDOUT);
            $line = fgets(STDIN);

            // Auto-resume if we auto-paused
            if (!$wasPaused) {
                $this->setPaused(false);
            }

            if ($line !== false) {
                return rtrim($line, "\r\n");
            }
        }

        return null;
    }
}
