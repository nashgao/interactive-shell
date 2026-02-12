<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Formatter;

use NashGao\InteractiveShell\Message\Message;

/**
 * Base message formatter with common formatting capabilities.
 *
 * Provides generic formatting features:
 * - Multiple output formats (compact, table, vertical, JSON, hex)
 * - Color support
 * - Field filtering (show/hide)
 * - Depth limiting for nested structures
 * - Schema mode for structure display
 *
 * Subclasses implement protocol-specific field extraction and formatting.
 */
abstract class BaseMessageFormatter implements MessageFormatterInterface
{
    protected const FORMAT_COMPACT = 'compact';

    protected const FORMAT_TABLE = 'table';

    protected const FORMAT_VERTICAL = 'vertical';

    protected const FORMAT_JSON = 'json';

    protected const FORMAT_HEX = 'hex';

    protected string $format = self::FORMAT_COMPACT;

    protected bool $colorEnabled = true;

    protected int $depthLimit = 0;

    protected bool $schemaMode = false;

    /** @var array<string> Fields to show (empty = show all) */
    protected array $showFields = [];

    /** @var array<string> Fields to hide */
    protected array $hideFields = [];

    protected int $payloadTruncation = 80;

    protected int $keyDisplayLength = 30;

    public function setFormat(string $format): void
    {
        $this->format = match ($format) {
            'compact', 'c' => self::FORMAT_COMPACT,
            'table', 't' => self::FORMAT_TABLE,
            'vertical', 'v' => self::FORMAT_VERTICAL,
            'json', 'j' => self::FORMAT_JSON,
            'hex', 'h' => self::FORMAT_HEX,
            default => self::FORMAT_COMPACT,
        };
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setColorEnabled(bool $enabled): void
    {
        $this->colorEnabled = $enabled;
    }

    /**
     * Set JSON depth limit for pretty printing.
     * 0 = unlimited depth.
     */
    public function setDepthLimit(int $depth): void
    {
        $this->depthLimit = max(0, $depth);
    }

    public function getDepthLimit(): int
    {
        return $this->depthLimit;
    }

    /**
     * Enable/disable schema mode.
     * In schema mode, JSON structure is shown without actual values.
     */
    public function setSchemaMode(bool $enabled): void
    {
        $this->schemaMode = $enabled;
    }

    /**
     * Check if schema mode is enabled.
     */
    public function isSchemaMode(): bool
    {
        return $this->schemaMode;
    }

    /**
     * Set fields to show (only these fields will be displayed).
     *
     * @param array<string> $fields
     */
    public function setShowFields(array $fields): void
    {
        $this->showFields = $fields;
        if (!empty($fields)) {
            $this->hideFields = []; // Clear hide fields when setting show fields
        }
    }

    /**
     * Set fields to hide.
     *
     * @param array<string> $fields
     */
    public function setHideFields(array $fields): void
    {
        $this->hideFields = $fields;
        if (!empty($fields)) {
            $this->showFields = []; // Clear show fields when setting hide fields
        }
    }

    /**
     * Clear all field filters.
     */
    public function clearFieldFilters(): void
    {
        $this->showFields = [];
        $this->hideFields = [];
    }

    /**
     * Format a message for display.
     */
    public function format(Message $message): string
    {
        return match ($this->format) {
            self::FORMAT_COMPACT => $this->formatCompact($message),
            self::FORMAT_TABLE => $this->formatTable($message),
            self::FORMAT_VERTICAL => $this->formatVertical($message),
            self::FORMAT_JSON => $this->formatJson($message),
            self::FORMAT_HEX => $this->formatHexDump($message),
            default => $this->formatCompact($message),
        };
    }

    // ─── Abstract Methods (Protocol-specific) ────────────────────────────

    /**
     * Format message in compact single-line format.
     */
    abstract protected function formatCompact(Message $message): string;

    /**
     * Format message as a table row.
     */
    abstract protected function formatTable(Message $message): string;

    /**
     * Format message in vertical detailed format (like MySQL \G).
     */
    abstract protected function formatVertical(Message $message): string;

    /**
     * Format message as JSON.
     */
    abstract protected function formatJson(Message $message): string;

    /**
     * Extract raw payload as string for hex dump.
     */
    abstract protected function extractRawPayload(Message $message): string;

    // ─── Hex Dump Formatting (Generic) ───────────────────────────────────

    /**
     * Format message in hex dump style.
     */
    protected function formatHexDump(Message $message): string
    {
        $lines = [];
        $lines[] = str_repeat('=', 80);

        $time = $message->timestamp->format(\DateTimeInterface::ATOM);
        $lines[] = sprintf('Timestamp: %s', $time);
        $lines[] = sprintf('Type:      %s', $message->type);
        $lines[] = sprintf('Source:    %s', $message->source);
        $lines[] = str_repeat('-', 80);
        $lines[] = '';

        $payload = $this->extractRawPayload($message);
        $lines[] = $this->formatHexBytes($payload);

        $lines[] = str_repeat('=', 80);

        return implode("\n", $lines);
    }

    /**
     * Format raw bytes as hex dump.
     *
     * Output format:
     * Offset    00 01 02 03 04 05 06 07 08 09 0A 0B 0C 0D 0E 0F  |ASCII           |
     * 00000000  48 65 6c 6c 6f 20 57 6f 72 6c 64 21 00 00 00 00  |Hello World!....|
     */
    public function formatHexBytes(string $data, int $bytesPerLine = 16): string
    {
        if ($data === '') {
            return '(empty payload)';
        }

        $lines = [];

        // Header
        $header = 'Offset    ';
        for ($i = 0; $i < $bytesPerLine; ++$i) {
            $header .= sprintf('%02X ', $i);
        }
        $header .= ' |ASCII' . str_repeat(' ', $bytesPerLine - 5) . '|';
        $lines[] = $header;

        // Data lines
        $length = strlen($data);
        $offset = 0;

        while ($offset < $length) {
            $chunk = substr($data, $offset, $bytesPerLine);
            $lines[] = $this->formatHexLine($chunk, $offset, $bytesPerLine);
            $offset += $bytesPerLine;
        }

        return implode("\n", $lines);
    }

    /**
     * Format a single hex dump line.
     */
    protected function formatHexLine(string $chunk, int $offset, int $bytesPerLine): string
    {
        $hex = '';
        $ascii = '';
        $length = strlen($chunk);

        // Format hex and ASCII representations
        for ($i = 0; $i < $length; ++$i) {
            $byte = ord($chunk[$i]);
            $hex .= sprintf('%02X ', $byte);

            // ASCII representation: printable chars only, else '.'
            $ascii .= ($byte >= 32 && $byte <= 126) ? $chunk[$i] : '.';
        }

        // Pad hex section if incomplete line
        if ($length < $bytesPerLine) {
            $hex .= str_repeat('   ', $bytesPerLine - $length);
            $ascii .= str_repeat(' ', $bytesPerLine - $length);
        }

        return sprintf('%08X  %s |%s|', $offset, $hex, $ascii);
    }

    // ─── Field Filtering (Generic) ───────────────────────────────────────

    /**
     * Apply field filters to data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function applyFieldFilters(array $data): array
    {
        if (empty($this->showFields) && empty($this->hideFields)) {
            return $data;
        }

        // If show fields are specified, only keep those
        if (!empty($this->showFields)) {
            return $this->filterToShowFields($data);
        }

        // Otherwise, hide specified fields
        return $this->filterHideFields($data);
    }

    /**
     * Filter data to only include show fields.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function filterToShowFields(array $data): array
    {
        $result = [];

        foreach ($this->showFields as $field) {
            if (str_contains($field, '.')) {
                $value = $this->getNestedValue($data, $field);
                if ($value !== null) {
                    $this->setNestedValue($result, $field, $value);
                }
            } elseif (array_key_exists($field, $data)) {
                $result[$field] = $data[$field];
            }
        }

        return $result;
    }

    /**
     * Filter data to hide specified fields.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function filterHideFields(array $data): array
    {
        $result = $data;

        foreach ($this->hideFields as $field) {
            if (str_contains($field, '.')) {
                $this->unsetNestedValue($result, $field);
            } else {
                unset($result[$field]);
            }
        }

        return $result;
    }

    /**
     * Get a nested value using dot notation.
     *
     * @param array<string, mixed> $data
     */
    protected function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Set a nested value using dot notation.
     *
     * @param array<string, mixed> $data
     */
    protected function setNestedValue(array &$data, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$data;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    /**
     * Unset a nested value using dot notation.
     *
     * @param array<string, mixed> $data
     */
    protected function unsetNestedValue(array &$data, string $path): void
    {
        $keys = explode('.', $path);
        $current = &$data;

        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                unset($current[$key]);
                return;
            }
            if (!isset($current[$key]) || !is_array($current[$key])) {
                return;
            }
            $current = &$current[$key];
        }
    }

    // ─── JSON Formatting (Generic) ───────────────────────────────────────

    /**
     * Format JSON with depth limiting and optional schema mode.
     */
    protected function formatJsonWithDepthAndSchema(mixed $data): string
    {
        // Apply schema mode first (converts values to type placeholders)
        if ($this->schemaMode && is_array($data)) {
            $data = $this->convertToSchema($data);
        }

        // Then apply depth limiting
        if ($this->depthLimit > 0 && is_array($data)) {
            $data = $this->collapseAtDepth($data, $this->depthLimit);
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * Convert data to schema representation (structure without values).
     */
    protected function convertToSchema(mixed $data, int $depth = 0): mixed
    {
        if ($data === null) {
            return '<null>';
        }

        if (is_bool($data)) {
            return '<boolean>';
        }

        if (is_int($data)) {
            return '<integer>';
        }

        if (is_float($data)) {
            return '<float>';
        }

        if (is_string($data)) {
            // Check if it's a JSON string
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->convertToSchema($decoded, $depth);
            }

            // Show string length for long strings
            $len = strlen($data);
            if ($len > 50) {
                return "<string[{$len}]>";
            }
            return '<string>';
        }

        if (!is_array($data)) {
            return '<unknown>';
        }

        // Check if it's a list (sequential array)
        if (array_is_list($data)) {
            $count = count($data);
            if ($count === 0) {
                return '[]';
            }

            // Show schema of first element with count
            $firstSchema = $this->convertToSchema($data[0], $depth + 1);
            return ["<array[{$count}]>" => $firstSchema];
        }

        // Associative array
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = $this->convertToSchema($value, $depth + 1);
        }

        return $result;
    }

    /**
     * Collapse data structures beyond the specified depth.
     */
    protected function collapseAtDepth(mixed $data, int $maxDepth, int $currentDepth = 0): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        if ($currentDepth >= $maxDepth) {
            // Collapse this level
            $isSequential = array_is_list($data);
            if ($isSequential) {
                $count = count($data);
                return "[... {$count} items]";
            }

            $keys = array_keys($data);
            $keyList = implode(', ', array_slice($keys, 0, 5));
            if (count($keys) > 5) {
                $keyList .= ', ...';
            }
            return "{... {$keyList}}";
        }

        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = $this->collapseAtDepth($value, $maxDepth, $currentDepth + 1);
        }
        return $result;
    }

    // ─── Utility Methods ─────────────────────────────────────────────────

    /**
     * Truncate text to a maximum length.
     */
    protected function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        return mb_substr($text, 0, $maxLength - 3) . '...';
    }

    /**
     * Apply color to text if color is enabled.
     */
    protected function colorize(string $text, string $color): string
    {
        if (!$this->colorEnabled) {
            return $text;
        }

        $codes = [
            'black' => '30',
            'red' => '31',
            'green' => '32',
            'yellow' => '33',
            'blue' => '34',
            'magenta' => '35',
            'cyan' => '36',
            'white' => '37',
            'gray' => '90',
        ];

        $code = $codes[$color] ?? '37';
        return "\033[{$code}m{$text}\033[0m";
    }

    /**
     * Format payload for compact display.
     */
    protected function formatPayloadCompact(mixed $payload, ?int $maxLength = null): string
    {
        $maxLength ??= $this->payloadTruncation;

        if (is_string($payload)) {
            return $this->truncate($payload, $maxLength);
        }

        if (is_array($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $this->truncate($json ?: '{}', $maxLength);
        }

        return $this->truncate((string) json_encode($payload), $maxLength);
    }

    /**
     * Format payload for pretty display.
     */
    protected function formatPayloadPretty(mixed $payload): string
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $decoded = $this->applyFieldFilters($decoded);
                return $this->formatJsonWithDepthAndSchema($decoded);
            }
            return $payload;
        }

        if (is_array($payload)) {
            $payload = $this->applyFieldFilters($payload);
            return $this->formatJsonWithDepthAndSchema($payload);
        }

        return $this->formatJsonWithDepthAndSchema($payload);
    }
}
