<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Command;

/**
 * Manages command aliases for the interactive shell.
 */
final class AliasManager
{
    /**
     * @var array<int, string>
     */
    private const BUILT_IN_COMMANDS = [
        'help',
        'exit',
        'quit',
        'status',
        'clear',
        'connect',
    ];

    /** @var array<string, string> */
    private array $aliases;

    /** @var array<string, string> */
    private readonly array $builtInAliases;

    /**
     * @param array<string, string> $defaultAliases Optional default aliases
     */
    public function __construct(array $defaultAliases = [])
    {
        $this->builtInAliases = $defaultAliases;
        $this->aliases = $defaultAliases;
    }

    /**
     * Expand alias at the start of input string.
     */
    public function expand(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            return $input;
        }

        $parts = preg_split('/\s+/', $input, 2);

        if ($parts === false || count($parts) === 0) {
            return $input;
        }

        $firstWord = $parts[0] ?? '';
        $rest = $parts[1] ?? '';

        if ($firstWord === '') {
            return $input;
        }

        if (!isset($this->aliases[$firstWord])) {
            return $input;
        }

        $expanded = $this->aliases[$firstWord];

        return $rest !== '' ? "{$expanded} {$rest}" : $expanded;
    }

    /**
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }

    public function isBuiltInAlias(string $alias): bool
    {
        return isset($this->builtInAliases[$alias]);
    }

    public function removeAlias(string $alias): bool
    {
        if (!isset($this->aliases[$alias])) {
            return false;
        }

        unset($this->aliases[$alias]);
        return true;
    }

    public function reset(): void
    {
        $this->aliases = $this->builtInAliases;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setAlias(string $alias, string $command): void
    {
        $alias = trim($alias);
        $command = trim($command);

        if ($alias === '') {
            throw new \InvalidArgumentException('Alias name cannot be empty');
        }

        if ($command === '') {
            throw new \InvalidArgumentException('Command expansion cannot be empty');
        }

        if (in_array($alias, self::BUILT_IN_COMMANDS, true)) {
            throw new \InvalidArgumentException(
                "Cannot create alias '{$alias}': conflicts with built-in shell command. "
                . 'Built-in commands: ' . implode(', ', self::BUILT_IN_COMMANDS)
            );
        }

        $this->aliases[$alias] = $command;
    }
}
