<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Formatter;

use NashGao\InteractiveShell\Message\Message;

/**
 * Interface for message formatters.
 *
 * Formatters transform Message objects into display strings
 * in various formats (compact, table, vertical, JSON, hex).
 */
interface MessageFormatterInterface
{
    /**
     * Format a message for display.
     */
    public function format(Message $message): string;

    /**
     * Set the output format.
     */
    public function setFormat(string $format): void;

    /**
     * Get the current output format.
     */
    public function getFormat(): string;

    /**
     * Enable or disable color output.
     */
    public function setColorEnabled(bool $enabled): void;

    /**
     * Set fields to show (only these fields will be displayed).
     *
     * @param array<string> $fields
     */
    public function setShowFields(array $fields): void;

    /**
     * Set fields to hide.
     *
     * @param array<string> $fields
     */
    public function setHideFields(array $fields): void;

    /**
     * Clear all field filters.
     */
    public function clearFieldFilters(): void;
}
