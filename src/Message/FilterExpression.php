<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Message;

/**
 * Client-side filter for incoming messages.
 *
 * Supports pattern-based filtering for topics, types, and sources.
 * Uses glob-style patterns (* for wildcard matching).
 */
final class FilterExpression
{
    /** @var array<string, string> */
    private array $filters = [];

    /**
     * Add a filter pattern.
     *
     * @param string $field Field to filter (type, source, topic)
     * @param string $pattern Glob pattern (e.g., "topic:sensors/*")
     */
    public function addFilter(string $field, string $pattern): self
    {
        $this->filters[$field] = $pattern;
        return $this;
    }

    /**
     * Clear all filters.
     */
    public function clear(): self
    {
        $this->filters = [];
        return $this;
    }

    /**
     * Parse a filter string like "topic:sensors/*" or "type:data".
     */
    public static function parse(string $filterString): self
    {
        $instance = new self();

        $parts = explode(' ', trim($filterString));
        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                [$field, $pattern] = explode(':', $part, 2);
                $instance->addFilter($field, $pattern);
            }
        }

        return $instance;
    }

    /**
     * Check if a message matches the current filters.
     */
    public function matches(Message $message): bool
    {
        if (empty($this->filters)) {
            return true; // No filters = match all
        }

        foreach ($this->filters as $field => $pattern) {
            $value = $this->getMessageField($message, $field);
            if ($value === null) {
                continue;
            }

            if (!$this->matchesPattern($value, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if there are any active filters.
     */
    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }

    /**
     * Get string representation of current filters.
     */
    public function toString(): string
    {
        if (empty($this->filters)) {
            return 'No filters (showing all)';
        }

        $parts = [];
        foreach ($this->filters as $field => $pattern) {
            $parts[] = "{$field}:{$pattern}";
        }

        return implode(' ', $parts);
    }

    /**
     * Get a message field value.
     */
    private function getMessageField(Message $message, string $field): ?string
    {
        return match ($field) {
            'type' => $message->type,
            'source' => $message->source,
            'topic' => $this->getStringFromMetadata($message->metadata, 'topic'),
            'channel' => $this->getStringFromMetadata($message->metadata, 'channel'),
            default => null,
        };
    }

    /**
     * Get a string value from metadata array.
     *
     * @param array<string, mixed> $metadata
     */
    private function getStringFromMetadata(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Match a value against a glob pattern.
     *
     * Supports * for wildcard matching (e.g., "sensors/*" matches "sensors/temp").
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Convert glob to regex
        $regex = str_replace(
            ['*', '?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        );

        return (bool) preg_match("/^{$regex}$/", $value);
    }
}
