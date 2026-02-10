<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\History;

/**
 * Interface for protocol-specific topic pattern matching.
 *
 * Allows MessageHistory to filter messages by topic pattern without
 * coupling to any specific protocol (MQTT, Kafka, etc.).
 */
interface TopicMatcherInterface
{
    /**
     * Check if a topic matches the given pattern.
     *
     * @param string $pattern The pattern to match against (may include wildcards)
     * @param string $topic The actual topic to check
     * @return bool True if the topic matches the pattern
     */
    public function matches(string $pattern, string $topic): bool;
}
