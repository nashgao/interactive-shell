<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\History;

use NashGao\InteractiveShell\Message\Message;

/**
 * Circular buffer for storing recent messages with search capability.
 *
 * Provides message history storage with configurable limit, FIFO trimming,
 * and search functionality. Supports optional protocol-specific topic matching
 * via TopicMatcherInterface.
 */
class MessageHistory
{
    /** @var array<int, Message> */
    private array $messages = [];

    private int $nextId = 1;

    private ?TopicMatcherInterface $topicMatcher = null;

    public function __construct(
        private readonly int $maxMessages = 500,
    ) {}

    /**
     * Set a topic matcher for protocol-specific filtering.
     */
    public function setTopicMatcher(?TopicMatcherInterface $matcher): void
    {
        $this->topicMatcher = $matcher;
    }

    /**
     * Add a message to history.
     *
     * @return int The assigned message ID
     */
    public function add(Message $message): int
    {
        $id = $this->nextId++;
        $this->messages[$id] = $message;

        // Trim if over limit (FIFO)
        if (count($this->messages) > $this->maxMessages) {
            $removeCount = count($this->messages) - $this->maxMessages;
            $keys = array_keys($this->messages);
            for ($i = 0; $i < $removeCount; ++$i) {
                unset($this->messages[$keys[$i]]);
            }
        }

        return $id;
    }

    /**
     * Get the last N messages.
     *
     * @return array<int, Message>
     */
    public function getLast(int $count = 20): array
    {
        return array_slice($this->messages, -$count, null, true);
    }

    /**
     * Get a specific message by ID.
     */
    public function get(int $id): ?Message
    {
        return $this->messages[$id] ?? null;
    }

    /**
     * Get the most recent message.
     */
    public function getLatest(): ?Message
    {
        if (empty($this->messages)) {
            return null;
        }
        /** @var Message */
        return end($this->messages);
    }

    /**
     * Get the ID of the most recent message.
     */
    public function getLatestId(): ?int
    {
        if (empty($this->messages)) {
            return null;
        }
        $keys = array_keys($this->messages);
        return end($keys) ?: null;
    }

    /**
     * Search messages by content (case-insensitive).
     *
     * Searches in the topic field and payload content.
     *
     * @return array<int, Message>
     */
    public function search(string $pattern, int $limit = 50): array
    {
        $results = [];
        $count = 0;

        // Search in reverse order (newest first)
        foreach (array_reverse($this->messages, true) as $id => $message) {
            if ($count >= $limit) {
                break;
            }

            if ($this->matchesSearch($message, $pattern)) {
                $results[$id] = $message;
                ++$count;
            }
        }

        return $results;
    }

    /**
     * Get messages filtered by topic pattern.
     *
     * If a TopicMatcherInterface is set, uses it for pattern matching.
     * Otherwise, falls back to exact string matching.
     *
     * @return array<int, Message>
     */
    public function getByTopic(string $topicPattern, int $limit = 50): array
    {
        $results = [];
        $count = 0;

        foreach (array_reverse($this->messages, true) as $id => $message) {
            if ($count >= $limit) {
                break;
            }

            $topic = $this->extractTopic($message);
            if ($topic !== null && $this->topicMatches($topicPattern, $topic)) {
                $results[$id] = $message;
                ++$count;
            }
        }

        return $results;
    }

    /**
     * Get total message count.
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Clear all messages.
     */
    public function clear(): void
    {
        $this->messages = [];
        $this->nextId = 1;
    }

    /**
     * Export messages as array.
     *
     * @return array<array<string, mixed>>
     */
    public function export(int $limit = 0): array
    {
        $messages = $limit > 0 ? $this->getLast($limit) : $this->messages;
        $result = [];

        foreach ($messages as $message) {
            $result[] = $message->toArray();
        }

        return $result;
    }

    /**
     * Check if a topic matches a pattern.
     *
     * Uses TopicMatcherInterface if set, otherwise exact string match.
     */
    protected function topicMatches(string $pattern, string $topic): bool
    {
        if ($this->topicMatcher !== null) {
            return $this->topicMatcher->matches($pattern, $topic);
        }

        // Fallback: exact string match
        return $pattern === $topic;
    }

    /**
     * Check if message matches search pattern.
     */
    protected function matchesSearch(Message $message, string $pattern): bool
    {
        $pattern = strtolower($pattern);

        // Search in topic
        $topic = $this->extractTopic($message);
        if ($topic !== null && str_contains(strtolower($topic), $pattern)) {
            return true;
        }

        // Search in payload
        $payload = $message->payload;
        if (is_string($payload) && str_contains(strtolower($payload), $pattern)) {
            return true;
        }
        if (is_array($payload)) {
            $messageContent = $payload['message'] ?? null;
            if (is_string($messageContent) && str_contains(strtolower($messageContent), $pattern)) {
                return true;
            }
            $json = json_encode($payload);
            if ($json !== false && str_contains(strtolower($json), $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract topic from message payload.
     *
     * Override in subclasses for protocol-specific extraction.
     */
    protected function extractTopic(Message $message): ?string
    {
        if (is_array($message->payload) && isset($message->payload['topic'])) {
            $topic = $message->payload['topic'];
            return is_string($topic) ? $topic : null;
        }
        return null;
    }
}
