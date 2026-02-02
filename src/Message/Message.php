<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Message;

use DateTimeImmutable;

/**
 * Represents an incoming message from a streaming transport.
 */
final readonly class Message
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $type,
        public mixed $payload,
        public string $source,
        public DateTimeImmutable $timestamp,
        public array $metadata = [],
    ) {}

    /**
     * Create a message from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : 'unknown';
        $source = isset($data['source']) && is_string($data['source']) ? $data['source'] : 'unknown';
        $timestampStr = isset($data['timestamp']) && is_string($data['timestamp']) ? $data['timestamp'] : null;
        /** @var array<string, mixed> $metadata */
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];

        // Parse timestamp with fallback to current time if invalid
        $timestamp = new DateTimeImmutable();
        if ($timestampStr !== null) {
            try {
                $timestamp = new DateTimeImmutable($timestampStr);
            } catch (\DateMalformedStringException) {
                // Use current time as fallback for invalid timestamps
            }
        }

        return new self(
            type: $type,
            payload: $data['payload'] ?? $data['data'] ?? null,
            source: $source,
            timestamp: $timestamp,
            metadata: $metadata,
        );
    }

    /**
     * Create a data message (e.g., MQTT message received).
     *
     * @param array<string, mixed> $metadata
     */
    public static function data(mixed $payload, string $source, array $metadata = []): self
    {
        return new self(
            type: 'data',
            payload: $payload,
            source: $source,
            timestamp: new DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    /**
     * Create a system message (e.g., connection status).
     *
     * @param array<string, mixed> $metadata
     */
    public static function system(string $message, array $metadata = []): self
    {
        return new self(
            type: 'system',
            payload: $message,
            source: 'system',
            timestamp: new DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    /**
     * Create an error message.
     *
     * @param array<string, mixed> $metadata
     */
    public static function error(string $error, array $metadata = []): self
    {
        return new self(
            type: 'error',
            payload: $error,
            source: 'system',
            timestamp: new DateTimeImmutable(),
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'payload' => $this->payload,
            'source' => $this->source,
            'timestamp' => $this->timestamp->format(DateTimeImmutable::ATOM),
            'metadata' => $this->metadata,
        ];
    }
}
