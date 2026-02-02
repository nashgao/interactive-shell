<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Message;

/**
 * Formats incoming messages for terminal display.
 */
final class MessageFormatter
{
    private bool $showTimestamp = true;
    private bool $showSource = true;
    private bool $colorEnabled = true;

    public function setShowTimestamp(bool $show): self
    {
        $this->showTimestamp = $show;
        return $this;
    }

    public function setShowSource(bool $show): self
    {
        $this->showSource = $show;
        return $this;
    }

    public function setColorEnabled(bool $enabled): self
    {
        $this->colorEnabled = $enabled;
        return $this;
    }

    /**
     * Format a message for display.
     */
    public function format(Message $message): string
    {
        $parts = [];

        // Timestamp
        if ($this->showTimestamp) {
            $time = $message->timestamp->format('H:i:s.v');
            $parts[] = $this->colorize("[{$time}]", 'gray');
        }

        // Type indicator
        $typeIndicator = $this->getTypeIndicator($message->type);
        $parts[] = $typeIndicator;

        // Source
        if ($this->showSource && $message->source !== 'system') {
            $parts[] = $this->colorize("<{$message->source}>", 'cyan');
        }

        // Topic (for data messages)
        $topic = $message->metadata['topic'] ?? null;
        if ($topic !== null && is_string($topic)) {
            $parts[] = $this->colorize("[{$topic}]", 'yellow');
        }

        // Payload
        $payload = $this->formatPayload($message->payload);
        $parts[] = $payload;

        return implode(' ', $parts);
    }

    /**
     * Format a compact single-line version.
     */
    public function formatCompact(Message $message): string
    {
        $time = $message->timestamp->format('H:i:s');
        $type = strtoupper(substr($message->type, 0, 1));
        $payload = $this->formatPayload($message->payload);

        // Truncate long payloads
        if (strlen($payload) > 80) {
            $payload = substr($payload, 0, 77) . '...';
        }

        return "[{$time}] {$type} {$payload}";
    }

    /**
     * Get colored type indicator.
     */
    private function getTypeIndicator(string $type): string
    {
        return match ($type) {
            'data' => $this->colorize('[DATA]', 'green'),
            'system' => $this->colorize('[SYS]', 'blue'),
            'error' => $this->colorize('[ERR]', 'red'),
            'info' => $this->colorize('[INFO]', 'cyan'),
            default => $this->colorize('[' . strtoupper($type) . ']', 'white'),
        };
    }

    /**
     * Format the message payload.
     */
    private function formatPayload(mixed $payload): string
    {
        if ($payload === null) {
            return '';
        }

        if (is_string($payload)) {
            return $payload;
        }

        if (is_array($payload)) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $encoded !== false ? $encoded : '[json encode error]';
        }

        if (is_scalar($payload)) {
            return (string) $payload;
        }

        return '[complex object]';
    }

    /**
     * Apply ANSI color to text if colors are enabled.
     */
    private function colorize(string $text, string $color): string
    {
        if (!$this->colorEnabled) {
            return $text;
        }

        $colors = [
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'blue' => "\033[34m",
            'magenta' => "\033[35m",
            'cyan' => "\033[36m",
            'white' => "\033[37m",
            'gray' => "\033[90m",
            'reset' => "\033[0m",
        ];

        $colorCode = $colors[$color] ?? $colors['white'];
        $reset = $colors['reset'];

        return "{$colorCode}{$text}{$reset}";
    }
}
