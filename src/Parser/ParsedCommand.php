<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Parser;

/**
 * Immutable value object representing a parsed shell command with its arguments and options.
 */
final readonly class ParsedCommand
{
    /**
     * @param string $command The command name
     * @param array<int, string> $arguments Positional arguments indexed from 0
     * @param array<string, mixed> $options Named options as key-value pairs
     * @param string $raw Original raw input string before parsing
     * @param bool $hasVerticalTerminator Whether input ended with MySQL-style \G terminator
     */
    public function __construct(
        public string $command,
        public array $arguments,
        public array $options,
        public string $raw,
        public bool $hasVerticalTerminator,
    ) {}

    /**
     * Creates an empty parsed command instance.
     */
    public static function empty(): self
    {
        return new self(
            command: '',
            arguments: [],
            options: [],
            raw: '',
            hasVerticalTerminator: false,
        );
    }

    /**
     * Retrieves a positional argument by index with optional default fallback.
     */
    public function getArgument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * Retrieves a named option value with optional default fallback.
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Checks whether a named option exists in the parsed command.
     */
    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }
}
