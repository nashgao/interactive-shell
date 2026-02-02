<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Parser;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Parser\ShellParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * TDD Specification-First Tests for ShellParser Edge Cases
 *
 * These tests specify expected behavior for edge cases consumers might encounter:
 * - Unclosed quotes (user forgets closing quote)
 * - Escaped backslashes at line end
 * - Empty quoted strings in arguments
 * - Vertical terminators with surrounding whitespace
 *
 * @internal
 */
#[CoversClass(ShellParser::class)]
final class ShellParserEdgeCasesTest extends TestCase
{
    private ShellParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ShellParser();
    }

    /**
     * Specification: When a consumer provides input with an unclosed quote,
     * the parser should handle it gracefully without throwing exceptions.
     *
     * Real-world scenario: User types `echo "hello` and presses enter.
     * The shell should not crash - it should either treat the quote as literal
     * or return a partial parse result.
     */
    public function testParseCommandWithUnclosedQuoteReturnsPartialResult(): void
    {
        $input = 'echo "hello';

        $result = $this->parser->parse($input);

        // SPECIFICATION: Parser must not throw exception on unclosed quotes
        self::assertInstanceOf(
            ParsedCommand::class,
            $result,
            'Parser should return ParsedCommand even with unclosed quote'
        );

        // SPECIFICATION: Command name should be extracted correctly
        self::assertSame(
            'echo',
            $result->command,
            'Command should be "echo" regardless of quote closure'
        );

        // SPECIFICATION: Arguments should be present (behavior may vary)
        // Consumer expects either: ["\"hello"] (literal quote) or ["hello"] (best-effort parsing)
        self::assertNotEmpty(
            $result->arguments,
            'Arguments should be captured even with unclosed quote'
        );

        // SPECIFICATION: Should not have vertical terminator
        self::assertFalse(
            $result->hasVerticalTerminator,
            'Unclosed quote input should not have vertical terminator'
        );
    }

    /**
     * Specification: When input ends with escaped backslash (\\),
     * the parser should preserve the literal backslash in arguments.
     *
     * Real-world scenario: User runs `echo test\\` expecting to see "test\"
     * The backslash should be treated as a literal character, not an escape.
     */
    public function testParseCommandWithEscapedBackslashAtEnd(): void
    {
        $input = 'echo test\\\\';

        $result = $this->parser->parse($input);

        self::assertInstanceOf(ParsedCommand::class, $result);

        // SPECIFICATION: Command should be parsed correctly
        self::assertSame('echo', $result->command);

        // SPECIFICATION: Escaped backslash at end should be preserved
        self::assertCount(
            1,
            $result->arguments,
            'Should have exactly one argument'
        );

        // SPECIFICATION: The backslash should appear in the argument
        $argument = $result->arguments[0];
        self::assertStringContainsString(
            '\\',
            $argument,
            'Argument should contain the escaped backslash'
        );

        // SPECIFICATION: Should not have vertical terminator
        self::assertFalse($result->hasVerticalTerminator);
    }

    /**
     * Specification: When input contains empty quoted strings like "",
     * the parser should handle them according to shell semantics.
     *
     * Real-world scenario: User runs `echo "" arg` or `command --flag ""`
     * Empty strings may be preserved or filtered based on consumer needs.
     */
    public function testParseCommandWithEmptyQuotedString(): void
    {
        $input = 'echo "" arg';

        $result = $this->parser->parse($input);

        self::assertInstanceOf(ParsedCommand::class, $result);

        // SPECIFICATION: Command should be extracted
        self::assertSame('echo', $result->command);

        // SPECIFICATION: Arguments should be present
        self::assertIsArray($result->arguments, 'Arguments should be an array');

        // SPECIFICATION: Empty quoted string handling (two valid behaviors):
        // Option A: Preserve empty string as argument: ["", "arg"]
        // Option B: Filter empty strings: ["arg"]
        // Both are valid shell behaviors - test that we get consistent result

        if (count($result->arguments) === 2) {
            // If preserving empty strings
            self::assertSame(
                '',
                $result->arguments[0],
                'First argument should be empty string if preserved'
            );
            self::assertSame(
                'arg',
                $result->arguments[1],
                'Second argument should be "arg"'
            );
        } elseif (count($result->arguments) === 1) {
            // If filtering empty strings
            self::assertSame(
                'arg',
                $result->arguments[0],
                'Only non-empty argument should be "arg"'
            );
        } else {
            self::fail(
                'Arguments should either preserve empty string (2 args) or filter it (1 arg), got: '
                . var_export($result->arguments, true)
            );
        }

        // SPECIFICATION: Should not have vertical terminator
        self::assertFalse($result->hasVerticalTerminator);
    }

    /**
     * Specification: When input has vertical terminator (\G) with surrounding spaces,
     * the parser should correctly detect the terminator.
     *
     * Real-world scenario: User types `select * from users \G` (common MySQL format)
     * The space before \G should not prevent detection of vertical output format.
     */
    public function testParseVerticalTerminatorWithSpaces(): void
    {
        $input = 'select * from users \\G';

        $result = $this->parser->parse($input);

        self::assertInstanceOf(ParsedCommand::class, $result);

        // SPECIFICATION: Command should be extracted
        self::assertSame(
            'select',
            $result->command,
            'Command should be "select"'
        );

        // SPECIFICATION: Vertical terminator should be detected despite spacing
        self::assertTrue(
            $result->hasVerticalTerminator,
            'Parser should detect \\G terminator even with space before it'
        );

        // SPECIFICATION: Arguments should not include the terminator
        self::assertIsArray($result->arguments);

        // The terminator should be stripped from arguments
        $fullArgs = implode(' ', $result->arguments);
        self::assertStringNotContainsString(
            '\\G',
            $fullArgs,
            'Arguments should not contain the \\G terminator itself'
        );

        // SPECIFICATION: SQL keywords should be in arguments
        self::assertStringContainsString(
            'from',
            $fullArgs,
            'Arguments should contain SQL keywords'
        );
    }
}
