<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface ShellInterface
{
    /**
     * Run the interactive shell REPL loop.
     *
     * @return int Exit code (0 = success)
     */
    public function run(InputInterface $input, OutputInterface $output): int;

    /**
     * Execute a single command.
     *
     * @return int Exit code (0 = success)
     */
    public function executeCommand(string $command, OutputInterface $output): int;

    /**
     * Check if the shell is currently running.
     */
    public function isRunning(): bool;

    /**
     * Stop the shell.
     */
    public function stop(): void;
}
