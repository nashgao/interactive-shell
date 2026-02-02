<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Formatter;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Formatter\OutputFormat;
use NashGao\InteractiveShell\Formatter\OutputFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutputFormatter::class)]
#[CoversClass(OutputFormat::class)]
final class OutputFormatterTest extends TestCase
{
    private OutputFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new OutputFormatter();
    }

    public function testFormatErrorResult(): void
    {
        $result = CommandResult::failure('Something went wrong');

        $output = $this->formatter->format($result);

        self::assertSame("Error: Something went wrong\n", $output);
    }

    public function testFormatSuccessWithMessage(): void
    {
        $result = CommandResult::success(null, 'Operation completed');

        $output = $this->formatter->format($result);

        self::assertSame("Operation completed\n", $output);
    }

    public function testFormatSuccessWithNoDataOrMessage(): void
    {
        $result = CommandResult::success();

        $output = $this->formatter->format($result);

        self::assertSame("Command completed successfully\n", $output);
    }

    public function testFormatTableWithTabularData(): void
    {
        $result = CommandResult::success([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $output = $this->formatter->format($result, OutputFormat::Table);

        self::assertStringContainsString('+', $output);
        self::assertStringContainsString('id', $output);
        self::assertStringContainsString('name', $output);
        self::assertStringContainsString('Alice', $output);
        self::assertStringContainsString('Bob', $output);
    }

    public function testFormatTableWithKeyValueData(): void
    {
        $result = CommandResult::success([
            'server' => 'localhost',
            'port' => 9501,
        ]);

        $output = $this->formatter->format($result, OutputFormat::Table);

        self::assertStringContainsString('Key', $output);
        self::assertStringContainsString('Value', $output);
        self::assertStringContainsString('server', $output);
        self::assertStringContainsString('localhost', $output);
    }

    public function testFormatTableWithEmptyData(): void
    {
        $result = CommandResult::success([]);

        $output = $this->formatter->format($result, OutputFormat::Table);

        self::assertSame("No results\n", $output);
    }

    public function testFormatTableWithScalarData(): void
    {
        $result = CommandResult::success('simple string');

        $output = $this->formatter->format($result, OutputFormat::Table);

        self::assertSame("simple string\n", $output);
    }

    public function testFormatJson(): void
    {
        $result = CommandResult::success(['key' => 'value', 'nested' => ['a' => 1]]);

        $output = $this->formatter->format($result, OutputFormat::Json);

        $decoded = json_decode(trim($output), true);
        self::assertSame(['key' => 'value', 'nested' => ['a' => 1]], $decoded);
    }

    public function testFormatJsonPrettyPrints(): void
    {
        $result = CommandResult::success(['key' => 'value']);

        $output = $this->formatter->format($result, OutputFormat::Json);

        // Pretty print includes newlines
        self::assertStringContainsString("\n", $output);
    }

    public function testFormatCsvWithTabularData(): void
    {
        $result = CommandResult::success([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $output = $this->formatter->format($result, OutputFormat::Csv);

        $lines = explode("\n", trim($output));
        self::assertSame('id,name', $lines[0]);
        self::assertSame('1,Alice', $lines[1]);
        self::assertSame('2,Bob', $lines[2]);
    }

    public function testFormatCsvWithKeyValueData(): void
    {
        $result = CommandResult::success(['key' => 'value']);

        $output = $this->formatter->format($result, OutputFormat::Csv);

        self::assertStringContainsString('Key,Value', $output);
        self::assertStringContainsString('key,value', $output);
    }

    public function testFormatCsvEscapesSpecialCharacters(): void
    {
        $result = CommandResult::success([
            ['text' => 'has,comma'],
            ['text' => 'has"quote'],
            ['text' => "has\nnewline"],
        ]);

        $output = $this->formatter->format($result, OutputFormat::Csv);

        self::assertStringContainsString('"has,comma"', $output);
        self::assertStringContainsString('"has""quote"', $output);
    }

    public function testFormatCsvWithEmptyData(): void
    {
        $result = CommandResult::success([]);

        $output = $this->formatter->format($result, OutputFormat::Csv);

        self::assertSame('', $output);
    }

    public function testFormatVerticalWithTabularData(): void
    {
        $result = CommandResult::success([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $output = $this->formatter->format($result, OutputFormat::Vertical);

        self::assertStringContainsString('*************************** 1. row', $output);
        self::assertStringContainsString('*************************** 2. row', $output);
        self::assertStringContainsString('id:', $output);
        self::assertStringContainsString('name:', $output);
        self::assertStringContainsString('Alice', $output);
        self::assertStringContainsString('Bob', $output);
    }

    public function testFormatVerticalWithKeyValueData(): void
    {
        $result = CommandResult::success([
            'server' => 'localhost',
            'port' => 9501,
        ]);

        $output = $this->formatter->format($result, OutputFormat::Vertical);

        self::assertStringContainsString('*************************** 1. row', $output);
        self::assertStringContainsString('server:', $output);
        self::assertStringContainsString('localhost', $output);
    }

    public function testFormatVerticalWithExecutionTime(): void
    {
        $result = CommandResult::success(
            [['id' => 1]],
            null,
            ['duration_ms' => 150]
        );

        $output = $this->formatter->format($result, OutputFormat::Vertical);

        self::assertStringContainsString('1 row in set', $output);
        self::assertStringContainsString('sec', $output);
    }

    public function testFormatVerticalWithEmptyData(): void
    {
        $result = CommandResult::success([]);

        $output = $this->formatter->format($result, OutputFormat::Vertical);

        self::assertSame("Empty set\n", $output);
    }

    public function testFormatVerticalWithScalarData(): void
    {
        $result = CommandResult::success('single value');

        $output = $this->formatter->format($result, OutputFormat::Vertical);

        self::assertStringContainsString('*************************** 1. row', $output);
        self::assertStringContainsString('value:', $output);
        self::assertStringContainsString('single value', $output);
    }

    public function testFormatHandlesBooleanValues(): void
    {
        $result = CommandResult::success(['active' => true, 'disabled' => false]);

        $output = $this->formatter->format($result, OutputFormat::Table);

        self::assertStringContainsString('true', $output);
        self::assertStringContainsString('false', $output);
    }

    public function testFormatHandlesNullValues(): void
    {
        $result = CommandResult::success(['value' => null]);

        $output = $this->formatter->format($result, OutputFormat::Table);

        // Null is converted to empty string
        self::assertStringContainsString('value', $output);
    }

    public function testFormatHandlesNestedArrays(): void
    {
        // When nested array is a value in key-value data, it's JSON encoded
        $result = CommandResult::success([
            ['key' => 'config', 'value' => ['a' => 1, 'b' => 2]],
        ]);

        $output = $this->formatter->format($result, OutputFormat::Table);

        // Nested arrays in values are JSON encoded
        self::assertStringContainsString('{"a":1,"b":2}', $output);
    }

    public function testFormatError(): void
    {
        $output = $this->formatter->formatError('Test error');

        self::assertSame("Error: Test error\n", $output);
    }

    public function testFormatSuccess(): void
    {
        $output = $this->formatter->formatSuccess('Test message');

        self::assertSame("Test message\n", $output);
    }

    #[DataProvider('outputFormatProvider')]
    public function testOutputFormatFromString(string $input, OutputFormat $expected): void
    {
        self::assertSame($expected, OutputFormat::fromString($input));
    }

    /**
     * @return array<string, array{string, OutputFormat}>
     */
    public static function outputFormatProvider(): array
    {
        return [
            'json lowercase' => ['json', OutputFormat::Json],
            'json uppercase' => ['JSON', OutputFormat::Json],
            'csv' => ['csv', OutputFormat::Csv],
            'vertical' => ['vertical', OutputFormat::Vertical],
            'table' => ['table', OutputFormat::Table],
            'unknown defaults to table' => ['unknown', OutputFormat::Table],
        ];
    }

    public function testOutputFormatIsTabular(): void
    {
        self::assertTrue(OutputFormat::Table->isTabular());
        self::assertTrue(OutputFormat::Vertical->isTabular());
        self::assertFalse(OutputFormat::Json->isTabular());
        self::assertFalse(OutputFormat::Csv->isTabular());
    }

    #[DataProvider('outputFormatTryFromStringProvider')]
    public function testOutputFormatTryFromString(string $input, ?OutputFormat $expected): void
    {
        self::assertSame($expected, OutputFormat::tryFromString($input));
    }

    /**
     * @return array<string, array{string, OutputFormat|null}>
     */
    public static function outputFormatTryFromStringProvider(): array
    {
        return [
            'json lowercase' => ['json', OutputFormat::Json],
            'json uppercase' => ['JSON', OutputFormat::Json],
            'csv' => ['csv', OutputFormat::Csv],
            'vertical' => ['vertical', OutputFormat::Vertical],
            'table' => ['table', OutputFormat::Table],
            'invalid returns null' => ['unknown', null],
            'empty returns null' => ['', null],
            'gibberish returns null' => ['xyz123', null],
        ];
    }

    public function testFormatTableAlignColumns(): void
    {
        $result = CommandResult::success([
            ['id' => 1, 'name' => 'A'],
            ['id' => 100, 'name' => 'Bob'],
        ]);

        $output = $this->formatter->format($result, OutputFormat::Table);

        // Check that separators are consistent (columns are aligned)
        $lines = explode("\n", $output);
        $separatorLength = strlen($lines[0]);

        foreach ($lines as $line) {
            if (str_starts_with($line, '+') || str_starts_with($line, '|')) {
                self::assertSame($separatorLength, strlen($line));
            }
        }
    }
}
