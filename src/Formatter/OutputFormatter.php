<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Formatter;

use NashGao\InteractiveShell\Command\CommandResult;

/**
 * Format CommandResult data for terminal display with multiple output formats.
 */
final class OutputFormatter implements OutputFormatterInterface
{
    public function format(CommandResult $result, OutputFormat $format = OutputFormat::Table): string
    {
        if (!$result->success) {
            return $this->formatError($result->error ?? 'Unknown error');
        }

        if ($result->data === null) {
            return $result->message !== null
                ? $this->formatSuccess($result->message)
                : $this->formatSuccess('Command completed successfully');
        }

        $executionTime = isset($result->metadata['duration_ms']) && is_numeric($result->metadata['duration_ms'])
            ? (float) $result->metadata['duration_ms'] / 1000.0
            : null;

        return match ($format) {
            OutputFormat::Json => $this->formatJson($result->data),
            OutputFormat::Csv => $this->formatCsv($result->data),
            OutputFormat::Vertical => $this->formatVertical($result->data, $executionTime),
            default => $this->formatTable($result->data),
        };
    }

    public function formatTable(mixed $data): string
    {
        if (!is_array($data)) {
            return $this->scalarToString($data) . "\n";
        }

        if ($data === []) {
            return "No results\n";
        }

        if ($this->isTableData($data)) {
            /** @var array<array<string, mixed>> $tableData */
            $tableData = $data;
            return $this->buildTable($tableData);
        }

        /** @var array<string, mixed> $kvData */
        $kvData = $data;
        return $this->buildKeyValueTable($kvData);
    }

    public function formatJson(mixed $data, bool $pretty = true): string
    {
        $flags = JSON_THROW_ON_ERROR;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        }

        return json_encode($data, $flags) . "\n";
    }

    public function formatCsv(mixed $data): string
    {
        if (!is_array($data)) {
            return $this->scalarToString($data) . "\n";
        }

        if ($data === []) {
            return '';
        }

        $output = '';

        if ($this->isTableData($data)) {
            /** @var array<array<string, mixed>> $data */
            $firstRow = reset($data);
            if ($firstRow === false) {
                return '';
            }

            $output .= $this->buildCsvLine(array_keys($firstRow));

            foreach ($data as $row) {
                /** @var array<null|bool|float|int|string> $rowValues */
                $rowValues = array_values($row);
                $output .= $this->buildCsvLine($rowValues);
            }

            return $output;
        }

        $output .= $this->buildCsvLine(['Key', 'Value']);
        foreach ($data as $key => $value) {
            $output .= $this->buildCsvLine([$key, $this->scalarToString($value)]);
        }

        return $output;
    }

    public function formatVertical(mixed $data, ?float $executionTime = null): string
    {
        if (!is_array($data)) {
            $output = "*************************** 1. row ***************************\n";
            $output .= "value: {$this->scalarToString($data)}\n";

            if ($executionTime !== null) {
                $output .= sprintf("1 row in set (%.2f sec)\n", $executionTime);
            }

            return $output;
        }

        if ($data === []) {
            return "Empty set\n";
        }

        $output = '';
        $rowNumber = 1;

        if ($this->isTableData($data)) {
            /** @var array<array<string, mixed>> $data */
            $firstRow = reset($data);
            if ($firstRow === false) {
                return "Empty set\n";
            }

            $fieldNames = array_keys($firstRow);
            $maxFieldLength = $this->getMaxFieldNameLength($fieldNames);

            foreach ($data as $row) {
                $output .= sprintf("*************************** %d. row ***************************\n", $rowNumber);

                foreach ($fieldNames as $fieldName) {
                    $value = $this->scalarToString($row[$fieldName] ?? '');
                    $output .= sprintf("%{$maxFieldLength}s: %s\n", $fieldName, $value);
                }

                ++$rowNumber;
            }
        } else {
            /** @var array<string, mixed> $data */
            $fieldNames = array_keys($data);
            $maxFieldLength = $this->getMaxFieldNameLength($fieldNames);

            $output .= "*************************** 1. row ***************************\n";

            foreach ($data as $fieldName => $value) {
                $valueStr = $this->scalarToString($value);
                $output .= sprintf("%{$maxFieldLength}s: %s\n", $fieldName, $valueStr);
            }

            $rowNumber = 2;
        }

        $totalRows = $rowNumber - 1;

        if ($executionTime !== null) {
            $output .= sprintf(
                "%d %s in set (%.2f sec)\n",
                $totalRows,
                $totalRows === 1 ? 'row' : 'rows',
                $executionTime
            );
        }

        return $output;
    }

    public function formatError(string $error): string
    {
        return "Error: {$error}\n";
    }

    public function formatSuccess(string $message): string
    {
        return "{$message}\n";
    }

    /**
     * @param array<null|bool|float|int|string> $fields
     */
    private function buildCsvLine(array $fields): string
    {
        $escaped = [];
        foreach ($fields as $field) {
            $value = $this->scalarToString($field);

            if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }

            $escaped[] = $value;
        }

        return implode(',', $escaped) . "\n";
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildKeyValueTable(array $data): string
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [
                'Key' => (string) $key,
                'Value' => $this->scalarToString($value),
            ];
        }

        return $this->buildTable($rows);
    }

    /**
     * @param array<string> $values
     * @param array<int> $widths
     */
    private function buildRow(array $values, array $widths): string
    {
        $parts = [];
        foreach ($values as $index => $value) {
            $parts[] = ' ' . str_pad($value, $widths[$index]) . ' ';
        }

        return '|' . implode('|', $parts) . "|\n";
    }

    /**
     * @param array<int> $widths
     */
    private function buildSeparator(array $widths): string
    {
        $parts = [];
        foreach ($widths as $width) {
            $parts[] = str_repeat('-', $width + 2);
        }

        return '+' . implode('+', $parts) . "+\n";
    }

    /**
     * @param array<array<string, mixed>> $data
     */
    private function buildTable(array $data): string
    {
        $firstRow = reset($data);
        if ($firstRow === false) {
            return "No results\n";
        }

        $headers = array_keys($firstRow);
        $widths = $this->calculateColumnWidths($headers, $data);

        $output = '';
        $separator = $this->buildSeparator($widths);

        $output .= $separator;
        $output .= $this->buildRow($headers, $widths);
        $output .= $separator;

        foreach ($data as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = $this->scalarToString($row[$header] ?? '');
            }
            $output .= $this->buildRow($values, $widths);
        }

        $output .= $separator;

        return $output;
    }

    /**
     * @param array<string> $headers
     * @param array<array<string, mixed>> $data
     * @return array<int>
     */
    private function calculateColumnWidths(array $headers, array $data): array
    {
        $widths = [];

        foreach ($headers as $header) {
            $widths[] = strlen($header);
        }

        foreach ($data as $row) {
            foreach ($headers as $index => $header) {
                $value = $this->scalarToString($row[$header] ?? '');
                $widths[$index] = max($widths[$index], strlen($value));
            }
        }

        return $widths;
    }

    /**
     * @param array<string> $fieldNames
     */
    private function getMaxFieldNameLength(array $fieldNames): int
    {
        $maxLength = 0;

        foreach ($fieldNames as $fieldName) {
            $length = strlen((string) $fieldName);
            if ($length > $maxLength) {
                $maxLength = $length;
            }
        }

        return $maxLength;
    }

    /**
     * @param array<mixed> $data
     */
    private function isTableData(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $firstValue = reset($data);

        if (!is_array($firstValue)) {
            return false;
        }

        return array_keys($firstValue) !== range(0, count($firstValue) - 1);
    }

    private function scalarToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }
}
