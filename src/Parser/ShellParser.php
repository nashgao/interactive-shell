<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Parser;

use NashGao\InteractiveShell\Command\AliasManager;

/**
 * Parse shell input into structured ParsedCommand objects.
 *
 * Handles complex shell syntax including quoted strings, escape sequences,
 * options (long and short), and special terminators like \G for vertical output.
 */
final class ShellParser
{
    /**
     * @param AliasManager|null $aliasManager Optional alias manager for command expansion
     */
    public function __construct(
        private readonly ?AliasManager $aliasManager = null,
    ) {}

    /**
     * Parse shell input into a structured ParsedCommand object.
     */
    public function parse(string $input): ParsedCommand
    {
        $originalInput = $input;
        $input = trim($input);

        if ($input === '') {
            return new ParsedCommand(
                command: '',
                arguments: [],
                options: [],
                raw: $originalInput,
                hasVerticalTerminator: false,
            );
        }

        // Detect and strip \G terminator
        $hasVerticalTerminator = $this->detectVerticalTerminator($input);

        // Expand aliases
        if ($this->aliasManager !== null) {
            $input = $this->aliasManager->expand($input);
        }

        // Tokenize input
        $tokens = $this->tokenize($input);

        if (empty($tokens)) {
            return new ParsedCommand(
                command: '',
                arguments: [],
                options: [],
                raw: $originalInput,
                hasVerticalTerminator: $hasVerticalTerminator,
            );
        }

        // Extract command name (first token)
        $command = array_shift($tokens);

        // Classify remaining tokens
        /** @var array<int, string> $arguments */
        $arguments = [];
        /** @var array<string, bool|string> $options */
        $options = [];

        // Track if -- separator has been encountered (POSIX: end of options)
        $optionsEnded = false;

        foreach ($tokens as $token) {
            // Handle -- separator (POSIX: marks end of options)
            if ($token === '--' && !$optionsEnded) {
                $optionsEnded = true;
                continue;
            }

            // After --, everything is a positional argument
            if ($optionsEnded) {
                $arguments[] = $token;
                continue;
            }

            $classified = $this->classifyToken($token);

            if ($classified['type'] === 'option') {
                $name = $classified['name'] ?? '';
                $options[$name] = $classified['value'];
            } else {
                $value = $classified['value'];
                if (is_string($value)) {
                    $arguments[] = $value;
                }
            }
        }

        return new ParsedCommand(
            command: $command,
            arguments: $arguments,
            options: $options,
            raw: $originalInput,
            hasVerticalTerminator: $hasVerticalTerminator,
        );
    }

    /**
     * Classify token as option or positional argument.
     *
     * @return array{type: string, name?: string, value: bool|string}
     */
    private function classifyToken(string $token): array
    {
        // Long option: --key=value or --flag
        if (str_starts_with($token, '--')) {
            $option = substr($token, 2);

            // Extract name (before = if present)
            $name = str_contains($option, '=')
                ? explode('=', $option, 2)[0]
                : $option;

            // Validate option name (must start with letter, alphanumeric + hyphens + underscores)
            if ($name === '' || !$this->isValidOptionName($name)) {
                return ['type' => 'argument', 'value' => $token];
            }

            if (str_contains($option, '=')) {
                $parts = explode('=', $option, 2);
                $value = $parts[1] ?? '';
                return ['type' => 'option', 'name' => $name, 'value' => $value];
            }

            return ['type' => 'option', 'name' => $option, 'value' => true];
        }

        // Short option: -f or -f=value
        if (str_starts_with($token, '-') && strlen($token) > 1) {
            $option = substr($token, 1);

            // Extract name (before = if present)
            $name = str_contains($option, '=')
                ? explode('=', $option, 2)[0]
                : $option;

            // Validate short option name (alphanumeric, typically single letter or bundled)
            if (!$this->isValidShortOptionName($name)) {
                return ['type' => 'argument', 'value' => $token];
            }

            if (str_contains($option, '=')) {
                $parts = explode('=', $option, 2);
                $value = $parts[1] ?? '';
                return ['type' => 'option', 'name' => $name, 'value' => $value];
            }

            return ['type' => 'option', 'name' => $option, 'value' => true];
        }

        // Positional argument
        return ['type' => 'argument', 'value' => $token];
    }

    /**
     * Validate long option name (must start with letter, allow alphanumeric, hyphens, underscores).
     */
    private function isValidOptionName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name) === 1;
    }

    /**
     * Validate short option name (must start with letter, allow alphanumeric for bundled options).
     */
    private function isValidShortOptionName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name) === 1;
    }

    /**
     * Detect and strip \G vertical terminator from input.
     */
    private function detectVerticalTerminator(string &$input): bool
    {
        if (str_ends_with($input, '\G')) {
            $stripped = substr($input, 0, -2);
            if ($stripped !== false) {
                $input = $stripped;
            }
            return true;
        }

        return false;
    }

    /**
     * Tokenize input string respecting quotes and escape sequences.
     *
     * @return array<int, string>
     */
    private function tokenize(string $input): array
    {
        $tokens = [];
        $current = '';
        $inQuote = false;
        $quoteChar = null;
        $escaped = false;
        $length = strlen($input);

        for ($i = 0; $i < $length; ++$i) {
            $char = $input[$i];

            // Handle escape sequences
            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            // Handle quote boundaries
            if (($char === '"' || $char === "'") && !$inQuote) {
                $inQuote = true;
                $quoteChar = $char;
                continue;
            }

            if ($inQuote && $char === $quoteChar) {
                $inQuote = false;
                $quoteChar = null;
                continue;
            }

            // Handle spaces (token separators outside quotes)
            if ($char === ' ' && !$inQuote) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
                continue;
            }

            // Regular character
            $current .= $char;
        }

        // Add final token if exists
        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }
}
