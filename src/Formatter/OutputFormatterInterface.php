<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Formatter;

use NashGao\InteractiveShell\Command\CommandResult;

interface OutputFormatterInterface
{
    /**
     * Format a CommandResult for display.
     */
    public function format(CommandResult $result, OutputFormat $format = OutputFormat::Table): string;

    /**
     * Format data as table.
     */
    public function formatTable(mixed $data): string;

    /**
     * Format data as JSON.
     */
    public function formatJson(mixed $data, bool $pretty = true): string;

    /**
     * Format data as CSV.
     */
    public function formatCsv(mixed $data): string;

    /**
     * Format data in vertical (MySQL \G) style.
     */
    public function formatVertical(mixed $data, ?float $executionTime = null): string;

    /**
     * Format an error message.
     */
    public function formatError(string $error): string;

    /**
     * Format a success message.
     */
    public function formatSuccess(string $message): string;
}
