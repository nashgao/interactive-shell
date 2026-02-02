<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Command;

use JsonSerializable;

/**
 * Immutable value object representing the result of a command execution.
 */
final readonly class CommandResult implements JsonSerializable
{
    /**
     * @param bool $success Whether the command succeeded
     * @param mixed $data Command-specific result payload
     * @param string|null $error Error message if success=false
     * @param string|null $message Human-readable summary message
     * @param array<string, mixed> $metadata Operational metadata (duration, counts, etc.)
     */
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $error = null,
        public ?string $message = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a success result.
     *
     * @param mixed $data Optional result data
     * @param string|null $message Optional success message
     * @param array<string, mixed> $metadata Optional metadata
     */
    public static function success(mixed $data = null, ?string $message = null, array $metadata = []): self
    {
        return new self(
            success: true,
            data: $data,
            error: null,
            message: $message,
            metadata: $metadata,
        );
    }

    /**
     * Create a failure result.
     *
     * @param string $error Error message
     * @param mixed $data Optional additional data
     * @param array<string, mixed> $metadata Optional metadata
     */
    public static function failure(string $error, mixed $data = null, array $metadata = []): self
    {
        return new self(
            success: false,
            data: $data,
            error: $error,
            message: null,
            metadata: $metadata,
        );
    }

    /**
     * Create a CommandResult from a response array.
     *
     * @param array<string, mixed> $response Response array
     */
    public static function fromResponse(array $response): self
    {
        /** @var array<string, mixed> $metadata */
        $metadata = isset($response['metadata']) && is_array($response['metadata']) ? $response['metadata'] : [];

        $data = $response['data'] ?? null;

        // If no explicit 'data' key, collect remaining fields as data
        if ($data === null) {
            $knownKeys = ['success', 'error', 'message', 'metadata', 'data'];
            $extraData = array_diff_key($response, array_flip($knownKeys));
            if ($extraData !== []) {
                $data = $extraData;
            }
        }

        return new self(
            success: isset($response['success']) && $response['success'] === true,
            data: $data,
            error: isset($response['error']) && is_string($response['error']) ? $response['error'] : null,
            message: isset($response['message']) && is_string($response['message']) ? $response['message'] : null,
            metadata: $metadata,
        );
    }

    /**
     * Create a CommandResult from an exception.
     *
     * @param array<string, mixed> $metadata
     */
    public static function fromException(\Throwable $e, array $metadata = []): self
    {
        return new self(
            success: false,
            data: null,
            error: $e->getMessage(),
            message: null,
            metadata: array_merge($metadata, [
                'exception' => get_class($e),
                'exit_code' => 2,
            ]),
        );
    }

    /**
     * Get the CLI exit code for this result.
     */
    public function getExitCode(): int
    {
        return $this->success ? 0 : 1;
    }

    /**
     * Create a new result with additional metadata merged.
     *
     * @param array<string, mixed> $metadata Additional metadata to merge
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            success: $this->success,
            data: $this->data,
            error: $this->error,
            message: $this->message,
            metadata: array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Create a new result with a message.
     */
    public function withMessage(string $message): self
    {
        return new self(
            success: $this->success,
            data: $this->data,
            error: $this->error,
            message: $message,
            metadata: $this->metadata,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['success' => $this->success];

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        if ($this->message !== null) {
            $result['message'] = $this->message;
        }

        if ($this->metadata !== []) {
            $result['metadata'] = $this->metadata;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
