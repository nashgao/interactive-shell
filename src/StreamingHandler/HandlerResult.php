<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\StreamingHandler;

/**
 * Result returned by streaming shell command handlers.
 *
 * Contains state changes that should be applied to the shell.
 * Subclasses may add protocol-specific state changes.
 */
final readonly class HandlerResult
{
    public function __construct(
        public bool $shouldExit = false,
        public ?bool $pauseState = null,
        public bool $success = true,
        public ?string $message = null,
    ) {}

    /**
     * Create a success result.
     */
    public static function success(?string $message = null): self
    {
        return new self(success: true, message: $message);
    }

    /**
     * Create a failure result.
     */
    public static function failure(string $message): self
    {
        return new self(success: false, message: $message);
    }

    /**
     * Create an exit result.
     */
    public static function exit(): self
    {
        return new self(shouldExit: true);
    }

    /**
     * Create a pause state change result.
     */
    public static function pause(): self
    {
        return new self(pauseState: true);
    }

    /**
     * Create a resume state change result.
     */
    public static function resume(): self
    {
        return new self(pauseState: false);
    }
}
