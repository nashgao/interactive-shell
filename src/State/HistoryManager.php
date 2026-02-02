<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\State;

/**
 * Manages command history for the interactive shell.
 */
final class HistoryManager
{
    private const DEFAULT_HISTORY_FILENAME = '.interactive_shell_history';
    private const DEFAULT_MAX_ENTRIES = 1000;
    private const SECURE_FILE_PERMISSIONS = 0600;

    /** @var array<int, string> */
    private array $history = [];
    private readonly string $historyFile;
    private readonly int $maxEntries;
    private int $position = 0;
    private readonly bool $readlineAvailable;

    public function __construct(
        int $maxEntries = self::DEFAULT_MAX_ENTRIES,
        ?string $historyFile = null,
    ) {
        $this->maxEntries = $maxEntries;
        $home = $_SERVER['HOME'] ?? '/tmp';
        $this->historyFile = $historyFile ?? $home . '/' . self::DEFAULT_HISTORY_FILENAME;
        $this->readlineAvailable = function_exists('readline_add_history');
        $this->load();
    }

    public function add(string $command): void
    {
        $command = trim($command);

        if ($command === '') {
            return;
        }

        $lastCommand = end($this->history);
        if ($lastCommand !== false && $lastCommand === $command) {
            return;
        }

        $this->history[] = $command;

        if (count($this->history) > $this->maxEntries) {
            $this->history = array_slice($this->history, -$this->maxEntries);
        }

        $this->position = count($this->history);

        if ($this->readlineAvailable) {
            readline_add_history($command);
        }
    }

    public function clear(): void
    {
        $this->history = [];
        $this->position = 0;
    }

    /**
     * @return array<int, string>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    public function getLast(): ?string
    {
        if (count($this->history) === 0) {
            return null;
        }

        return $this->history[count($this->history) - 1];
    }

    public function load(): void
    {
        if (!file_exists($this->historyFile)) {
            return;
        }

        if (!is_readable($this->historyFile)) {
            error_log("History file not readable: {$this->historyFile}");
            return;
        }

        $contents = file_get_contents($this->historyFile);
        if ($contents === false) {
            error_log("Failed to read history file: {$this->historyFile}");
            return;
        }

        $lines = explode("\n", $contents);
        $loadedHistory = [];

        foreach ($lines as $line) {
            $command = trim($line);
            if ($command === '') {
                continue;
            }

            $lastCommand = end($loadedHistory);
            if ($lastCommand !== false && $lastCommand === $command) {
                continue;
            }

            $loadedHistory[] = $command;
        }

        if (count($loadedHistory) > $this->maxEntries) {
            $loadedHistory = array_slice($loadedHistory, -$this->maxEntries);
        }

        $this->history = $loadedHistory;
        $this->position = count($this->history);
    }

    public function next(): ?string
    {
        if ($this->position >= count($this->history)) {
            return null;
        }

        ++$this->position;

        if ($this->position >= count($this->history)) {
            return null;
        }

        return $this->history[$this->position];
    }

    public function previous(): ?string
    {
        if (count($this->history) === 0) {
            return null;
        }

        if ($this->position > 0) {
            --$this->position;
        }

        return $this->history[$this->position] ?? null;
    }

    public function save(): void
    {
        $dir = dirname($this->historyFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log("Failed to create history directory: {$dir}");
            return;
        }

        $tempFile = $this->historyFile . '.tmp.' . getmypid();
        $handle = fopen($tempFile, 'w');

        if ($handle === false) {
            error_log("Failed to open temporary history file: {$tempFile}");
            return;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            error_log('Could not acquire lock on history file (another shell saving?)');
            fclose($handle);
            @unlink($tempFile);
            return;
        }

        $contents = implode("\n", $this->history);
        if (fwrite($handle, $contents) === false) {
            error_log("Failed to write history file: {$tempFile}");
            flock($handle, LOCK_UN);
            fclose($handle);
            @unlink($tempFile);
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        if (!rename($tempFile, $this->historyFile)) {
            error_log("Failed to rename temporary history file to: {$this->historyFile}");
            @unlink($tempFile);
            return;
        }

        if (!chmod($this->historyFile, self::SECURE_FILE_PERMISSIONS)) {
            error_log("Failed to set secure permissions on history file: {$this->historyFile}");
        }
    }
}
