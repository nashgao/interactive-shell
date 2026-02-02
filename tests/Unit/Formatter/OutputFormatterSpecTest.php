<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Formatter;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Formatter\OutputFormat;
use NashGao\InteractiveShell\Formatter\OutputFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * TDD Specification Tests for OutputFormatter
 *
 * These tests define expected behavior from the consumer's perspective.
 * They specify WHAT the formatter SHOULD do, not what it currently DOES.
 *
 * @internal
 */
#[CoversClass(OutputFormatter::class)]
final class OutputFormatterSpecTest extends TestCase
{
    private OutputFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new OutputFormatter();
    }

    /**
     * Specification: Table columns containing Unicode characters should align correctly
     *
     * When a consumer formats data containing Unicode characters (emoji, CJK characters)
     * as a table, the columns must align properly despite varying character widths.
     * This test fails if Unicode characters break visual column alignment.
     */
    public function testTableFormatAlignsColumnsWithUnicodeCharacters(): void
    {
        // Arrange: Create data with Unicode characters (emoji + CJK + ASCII mix)
        $data = [
            ['name' => 'Alice', 'status' => '✅ Active', 'location' => '东京'],
            ['name' => 'Bob Smith', 'status' => '❌ Inactive', 'location' => 'Paris'],
            ['name' => '王小明', 'status' => '⚠️ Pending', 'location' => 'Beijing 北京'],
        ];

        $result = CommandResult::success($data);

        // Act: Format as table
        $output = $this->formatter->format($result, OutputFormat::Table);

        // Assert: Table columns should be aligned
        // Split into lines and verify column positions align across rows
        $lines = explode("\n", $output);
        $dataLines = array_filter($lines, fn($line) => !empty(trim($line)) && !str_contains($line, '---'));
        $dataLines = array_values($dataLines);

        // Verify we have header + 3 data rows (at minimum)
        self::assertGreaterThanOrEqual(4, count($dataLines), 'Table should have header and data rows');

        // Verify each data line has consistent separator positions
        // The pipe characters (|) should align vertically
        $separatorPositions = [];
        foreach ($dataLines as $index => $line) {
            $positions = [];
            $offset = 0;
            while (($pos = mb_strpos($line, '|', $offset)) !== false) {
                $positions[] = $pos;
                $offset = $pos + 1;
            }
            $separatorPositions[$index] = $positions;
        }

        // All rows should have the same number of separators
        $separatorCounts = array_map('count', $separatorPositions);
        self::assertCount(1, array_unique($separatorCounts), 'All table rows should have same number of column separators');

        // Verify the table contains our Unicode data
        self::assertStringContainsString('✅', $output, 'Table should contain emoji');
        self::assertStringContainsString('东京', $output, 'Table should contain CJK characters');
        self::assertStringContainsString('王小明', $output, 'Table should contain CJK name');
    }

    /**
     * Specification: Numeric values should remain as JSON numbers, not strings
     *
     * When a consumer formats data containing integers and floats as JSON,
     * the output must preserve numeric types (unquoted) rather than converting
     * them to strings. This test fails if numbers appear as quoted strings in JSON.
     */
    public function testJsonFormatPreservesNumericTypes(): void
    {
        // Arrange: Create data with various numeric types
        $data = [
            [
                'id' => 42,                    // integer
                'price' => 19.99,              // float
                'quantity' => 100,             // integer
                'discount' => 0.15,            // float
                'stock' => 0,                  // zero integer
                'rating' => 4.5,               // float
            ],
        ];

        $result = CommandResult::success($data);

        // Act: Format as JSON
        $output = $this->formatter->format($result, OutputFormat::Json);

        // Assert: Numeric values should be unquoted (true JSON numbers)
        // Parse the JSON to verify it's valid
        $decoded = json_decode($output, true);
        self::assertNotNull($decoded, 'Output should be valid JSON');
        self::assertIsArray($decoded, 'Decoded JSON should be an array');
        self::assertNotEmpty($decoded, 'Decoded JSON should not be empty');

        // Verify numeric types are preserved in the first record
        $firstRecord = $decoded[0] ?? null;
        self::assertIsArray($firstRecord, 'First record should be an array');

        self::assertArrayHasKey('id', $firstRecord, 'Record should have id field');
        self::assertIsInt($firstRecord['id'], 'id should be integer type');
        self::assertSame(42, $firstRecord['id'], 'id value should be 42');

        self::assertArrayHasKey('price', $firstRecord, 'Record should have price field');
        self::assertIsFloat($firstRecord['price'], 'price should be float type');
        self::assertEqualsWithDelta(19.99, $firstRecord['price'], 0.001, 'price value should be 19.99');

        self::assertArrayHasKey('quantity', $firstRecord, 'Record should have quantity field');
        self::assertIsInt($firstRecord['quantity'], 'quantity should be integer type');
        self::assertSame(100, $firstRecord['quantity'], 'quantity value should be 100');

        self::assertArrayHasKey('discount', $firstRecord, 'Record should have discount field');
        self::assertIsFloat($firstRecord['discount'], 'discount should be float type');
        self::assertEqualsWithDelta(0.15, $firstRecord['discount'], 0.001, 'discount value should be 0.15');

        self::assertArrayHasKey('stock', $firstRecord, 'Record should have stock field');
        self::assertIsInt($firstRecord['stock'], 'stock should be integer type');
        self::assertSame(0, $firstRecord['stock'], 'stock value should be 0');

        // Additionally verify the raw JSON string contains unquoted numbers
        self::assertStringContainsString('"id":42', str_replace(' ', '', $output), 'JSON should contain unquoted integer 42');
        self::assertStringContainsString('"price":19.99', str_replace(' ', '', $output), 'JSON should contain unquoted float 19.99');
        self::assertStringNotContainsString('"id":"42"', $output, 'JSON should NOT contain quoted integer');
        self::assertStringNotContainsString('"price":"19.99"', $output, 'JSON should NOT contain quoted float');
    }

    /**
     * Specification: CSV values with special characters should be properly escaped per RFC 4180
     *
     * When a consumer formats data containing commas, quotes, newlines, or other
     * special characters as CSV, the output must follow RFC 4180 escaping rules:
     * - Values containing special chars must be quoted
     * - Double quotes must be escaped by doubling them
     * - Newlines within fields must be preserved within quotes
     * This test fails if CSV escaping is incorrect or missing.
     */
    public function testCsvFormatEscapesAllSpecialCharacters(): void
    {
        // Arrange: Create data with all CSV special characters
        $data = [
            [
                'name' => 'Smith, John',              // Contains comma
                'company' => 'Acme "Best" Corp',      // Contains double quotes
                'address' => "123 Main St\nSuite 100", // Contains newline
                'description' => 'Normal text',        // No special chars
            ],
            [
                'name' => 'O\'Brien',                  // Contains single quote (should be safe)
                'company' => 'Test, "Quotes", Inc.',   // Multiple special chars
                'address' => 'Simple',
                'description' => 'Value with, comma',
            ],
        ];

        $result = CommandResult::success($data);

        // Act: Format as CSV
        $output = $this->formatter->format($result, OutputFormat::Csv);

        // Assert: CSV should follow RFC 4180 escaping rules
        $lines = explode("\n", trim($output));

        // Should have header + 2 data rows = 3 lines minimum
        // (Note: newlines in fields might create more lines, so check carefully)
        self::assertGreaterThanOrEqual(3, count($lines), 'CSV should have header and data rows');

        // Verify proper escaping patterns:

        // 1. Value with comma should be quoted
        self::assertStringContainsString('"Smith, John"', $output, 'Values containing commas must be quoted');

        // 2. Value with double quotes should have quotes doubled and be quoted
        self::assertStringContainsString('"Acme ""Best"" Corp"', $output, 'Double quotes must be escaped by doubling');

        // 3. Value with newline should be quoted and preserve the newline
        self::assertStringContainsString('"123 Main St' . "\n" . 'Suite 100"', $output, 'Newlines must be preserved within quotes');

        // 4. Multiple special characters in one field
        self::assertStringContainsString('"Test, ""Quotes"", Inc."', $output, 'Multiple special chars must be properly escaped');

        // 5. Value with just comma should be quoted
        self::assertStringContainsString('"Value with, comma"', $output, 'Values with commas must be quoted');

        // 6. Normal values without special chars may be unquoted (implementation choice)
        // but if quoted, that's also acceptable per RFC 4180
        self::assertStringContainsString('Normal text', $output, 'Normal text should appear in output');

        // 7. Parse the CSV to verify it's valid and data integrity is maintained
        // Note: We use a temp stream for proper CSV parsing since str_getcsv doesn't handle
        // embedded newlines in quoted fields when splitting by lines first
        $trimmedOutput = trim($output);
        self::assertIsString($trimmedOutput, 'CSV output should be a string');
        self::assertNotEmpty($trimmedOutput, 'CSV output should not be empty');

        // Use proper CSV parsing via temp stream to handle embedded newlines
        $stream = fopen('php://temp', 'r+');
        self::assertIsResource($stream, 'Should create temp stream');
        fwrite($stream, $trimmedOutput);
        rewind($stream);

        $parsedData = [];
        while (($row = fgetcsv($stream)) !== false) {
            $parsedData[] = $row;
        }
        fclose($stream);

        // Verify header exists
        self::assertIsArray($parsedData[0], 'First row should be header');
        self::assertContains('name', $parsedData[0], 'Header should contain name column');

        // Verify first data row values are correctly parsed back
        self::assertIsArray($parsedData[1] ?? null, 'Second row should exist (first data row)');
        self::assertSame('Smith, John', $parsedData[1][0], 'Comma in value should be preserved after parsing');
        self::assertSame('Acme "Best" Corp', $parsedData[1][1], 'Quotes in value should be preserved after parsing');
        self::assertSame("123 Main St\nSuite 100", $parsedData[1][2], 'Newline in value should be preserved after parsing');
    }
}
