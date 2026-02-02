<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Formatter;

/**
 * Output format options for shell command results.
 */
enum OutputFormat: string
{
    case Table = 'table';
    case Json = 'json';
    case Csv = 'csv';
    case Vertical = 'vertical';

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'table' => self::Table,
            'json' => self::Json,
            'csv' => self::Csv,
            'vertical' => self::Vertical,
            default => self::Table,
        };
    }

    /**
     * Try to create OutputFormat from string, returning null if invalid.
     */
    public static function tryFromString(string $value): ?self
    {
        return match (strtolower($value)) {
            'table' => self::Table,
            'json' => self::Json,
            'csv' => self::Csv,
            'vertical' => self::Vertical,
            default => null,
        };
    }

    public function isTabular(): bool
    {
        return $this === self::Table || $this === self::Vertical;
    }
}
