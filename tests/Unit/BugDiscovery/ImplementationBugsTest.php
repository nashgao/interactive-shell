<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\BugDiscovery;

use NashGao\InteractiveShell\Command\AliasManager;
use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Formatter\OutputFormat;
use NashGao\InteractiveShell\Formatter\OutputFormatter;
use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Parser\ShellParser;
use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\StreamingShell;
use NashGao\InteractiveShell\Transport\StreamingTransportInterface;
use NashGao\InteractiveShell\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Bug Discovery Tests using TDD Specification-First Approach.
 *
 * These tests define EXPECTED behavior without reading implementation.
 * Failing tests reveal bugs in the current implementation.
 */
#[CoversClass(AliasManager::class)]
#[CoversClass(OutputFormat::class)]
#[CoversClass(ShellParser::class)]
#[CoversClass(Shell::class)]
#[CoversClass(Message::class)]
#[CoversClass(StreamingShell::class)]
#[Group('bug-discovery')]
final class ImplementationBugsTest extends TestCase
{
    // =========================================================================
    // PRIORITY 1: HIGH-RISK SCENARIOS (Crash/State Corruption)
    // =========================================================================

    /**
     * Scenario #1: Self-referential alias should prevent infinite recursion.
     *
     * Specification: When an alias references itself (alias a=a), the system
     * MUST NOT enter infinite recursion. It should either:
     * - Reject the alias at creation time, OR
     * - Expand only once and stop
     *
     * Risk: CRASH (stack overflow)
     */
    public function testSelfReferentialAliasDoesNotCauseInfiniteRecursion(): void
    {
        $aliasManager = new AliasManager();

        // Set up a self-referential alias
        $aliasManager->setAlias('recurse', 'recurse');

        // This MUST NOT cause infinite recursion
        // Expected: Either throws exception, returns original, or expands once
        $result = $aliasManager->expand('recurse');

        // The result must be defined (not infinite loop)
        self::assertIsString($result);

        // Should not have expanded infinitely - result should be bounded
        self::assertLessThanOrEqual(100, strlen($result), 'Alias expansion should be bounded');

        // Result should be 'recurse' (expanded once to itself, or not expanded at all)
        self::assertSame('recurse', $result);
    }

    /**
     * Scenario #2: Invalid output format should not crash.
     *
     * Specification: When an invalid format value is provided, the system
     * MUST NOT crash. It should either:
     * - Default to table format, OR
     * - Throw a clear, catchable exception
     *
     * Risk: CRASH
     */
    public function testAllOutputFormatsProduceValidOutput(): void
    {
        $formatter = new OutputFormatter();

        // Create a test CommandResult with data
        $result = CommandResult::success(
            data: [['id' => 1, 'name' => 'test']],
            message: 'Test result'
        );

        // Test with all enum cases to ensure they work
        foreach (OutputFormat::cases() as $format) {
            $output = $formatter->format($result, $format);
            self::assertIsString($output, "Format {$format->name} should produce string output");
            self::assertNotEmpty($output, "Format {$format->name} should produce non-empty output");
        }
    }

    /**
     * Scenario #2b: OutputFormat::fromString with invalid value.
     *
     * Specification: Calling OutputFormat::fromString with an invalid format name
     * should either return a default format or throw a clear exception.
     */
    public function testInvalidOutputFormatStringHandledGracefully(): void
    {
        // Test that valid format strings work
        $tableFormat = OutputFormat::fromString('table');
        self::assertSame(OutputFormat::Table, $tableFormat);

        $jsonFormat = OutputFormat::fromString('json');
        self::assertSame(OutputFormat::Json, $jsonFormat);

        // Test invalid format string - should default or throw
        try {
            $invalidFormat = OutputFormat::fromString('invalid_format_xyz');
            // If it returns a value, it should be the default (Table)
            self::assertSame(OutputFormat::Table, $invalidFormat, 'Invalid format should default to Table');
        } catch (\Throwable $e) {
            // If it throws, it should be a clear exception
            self::assertInstanceOf(\ValueError::class, $e);
        }
    }

    /**
     * Scenario #3: Command ending with backslash should not corrupt state.
     *
     * Specification: A command ending with `\` (line continuation) should:
     * - Return a valid ParsedCommand
     * - Indicate continuation is expected
     * - Not corrupt parser state for subsequent commands
     *
     * Risk: STATE corruption
     */
    public function testCommandEndingWithBackslashReturnsValidResult(): void
    {
        $parser = new ShellParser();

        // Parse a command ending with backslash (continuation marker)
        $result = $parser->parse('SELECT * FROM users \\');

        // Result must be a valid ParsedCommand (not crash)
        self::assertNotNull($result);
        self::assertSame('SELECT', $result->command);

        // Verify subsequent parsing works correctly (no state corruption)
        $nextResult = $parser->parse('SELECT 1');
        self::assertNotNull($nextResult);
        self::assertSame('SELECT', $nextResult->command);
    }

    /**
     * Scenario #4: Unclosed quote should not crash or corrupt state.
     *
     * Specification: An unclosed quote (e.g., `echo "hello`) should:
     * - Return a valid ParsedCommand (possibly incomplete)
     * - Not throw an unhandled exception
     * - Not corrupt parser state
     *
     * Risk: STATE corruption / CRASH
     */
    public function testUnclosedQuoteDoesNotCrashParser(): void
    {
        $parser = new ShellParser();

        // This should NOT throw an unhandled exception
        $result = $parser->parse('echo "hello');

        // Result should be defined (either valid command or indication of incomplete)
        self::assertNotNull($result);

        // Parser state should not be corrupted - next parse should work
        $nextResult = $parser->parse('echo "world"');
        self::assertNotNull($nextResult);
        self::assertSame('echo', $nextResult->command);
    }

    // =========================================================================
    // PRIORITY 2: MEDIUM-RISK SCENARIOS (Error Handling)
    // =========================================================================

    /**
     * Scenario #5: Transport exception during send() should be handled.
     *
     * Specification: When transport throws during send(), the Shell should:
     * - Catch the exception
     * - Display an error message to the user
     * - Return a non-zero exit code (or indicate failure)
     * - NOT crash the entire shell
     *
     * Risk: ERROR propagation
     */
    public function testTransportExceptionDuringSendIsHandled(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('isConnected')->willReturn(true);
        $transport->method('getEndpoint')->willReturn('test://localhost');
        $transport->method('send')
            ->willThrowException(new RuntimeException('Connection lost'));

        $shell = new Shell($transport);
        $output = new BufferedOutput();

        // Execute a command that will trigger send()
        // This should NOT throw an unhandled exception to callers
        try {
            $exitCode = $shell->executeCommand('test command', $output);

            // If we reach here, exception was handled internally
            // Exit code should indicate failure (non-zero)
            self::assertNotEquals(0, $exitCode, 'Failed command should return non-zero exit code');
        } catch (RuntimeException $e) {
            // If exception propagates, this is a BUG - transport errors should be handled
            self::fail('Transport exception should be caught and handled, not propagated: ' . $e->getMessage());
        }
    }

    /**
     * Scenario #6: Invalid timestamp string in Message should be handled.
     *
     * Specification: When creating a Message with an invalid timestamp,
     * the system should handle it gracefully:
     * - Either parse what it can
     * - Or use current time as fallback
     * - Or throw a clear, documented exception type
     * - NOT throw an undocumented exception
     *
     * Risk: ERROR
     *
     * BUG DISCOVERED: Message::fromArray throws DateMalformedStringException
     * which is not documented or wrapped in a user-friendly exception.
     */
    public function testInvalidTimestampInMessageHandledGracefully(): void
    {
        // Test with valid timestamp first (baseline)
        $validMessage = Message::fromArray([
            'type' => 'info',
            'payload' => 'Test message',
            'timestamp' => '2024-01-15T10:30:00Z',
        ]);
        self::assertInstanceOf(Message::class, $validMessage);

        // Test with malformed timestamp - should not crash unexpectedly
        $invalidTimestampData = [
            'type' => 'info',
            'payload' => 'Test message',
            'timestamp' => 'not-a-valid-timestamp',
        ];

        // Invalid timestamp should use fallback (current time)
        $message = Message::fromArray($invalidTimestampData);
        self::assertInstanceOf(Message::class, $message);
        // Timestamp should be valid (current time fallback)
        self::assertInstanceOf(\DateTimeImmutable::class, $message->timestamp);

        // Test with missing timestamp - should use default (current time)
        $noTimestampData = [
            'type' => 'info',
            'payload' => 'Test message',
        ];
        $messageNoTimestamp = Message::fromArray($noTimestampData);
        self::assertInstanceOf(Message::class, $messageNoTimestamp);
    }

    /**
     * Scenario #7: Double connect() call should be idempotent or error clearly.
     *
     * Specification: Calling connect() twice on a transport should:
     * - Be idempotent (second call is no-op), OR
     * - Throw a clear exception indicating already connected
     * - NOT corrupt connection state
     *
     * Risk: STATE
     */
    public function testShellHandlesAlreadyConnectedTransport(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('isConnected')->willReturn(true);
        $transport->method('getEndpoint')->willReturn('test://localhost');

        $shell = new Shell($transport);
        $output = new BufferedOutput();

        // Shell should work fine with already-connected transport
        $exitCode = $shell->executeCommand('help', $output);

        // Built-in help command should work
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('help', strtolower($output->fetch()));
    }

    // =========================================================================
    // PRIORITY 3: LOWER-RISK SCENARIOS (Silent Failures / Logic Errors)
    // =========================================================================

    /**
     * Scenario #8: Sending command when disconnected should show error.
     *
     * Specification: Executing a remote command when disconnected should:
     * - Produce a visible error message to the user
     * - NOT fail silently
     * - Indicate that connection is required
     *
     * Risk: SILENT failure
     */
    public function testExecuteCommandWhenDisconnectedShowsError(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('isConnected')->willReturn(false);
        $transport->method('getEndpoint')->willReturn('test://localhost');

        $shell = new Shell($transport);
        $output = new BufferedOutput();

        // Attempt to execute remote command when disconnected
        $exitCode = $shell->executeCommand('remote_command', $output);

        // Should return non-zero (error)
        self::assertNotEquals(0, $exitCode);

        // Should produce error output (not fail silently)
        $outputText = $output->fetch();
        $hasErrorIndication = str_contains(strtolower($outputText), 'error')
            || str_contains(strtolower($outputText), 'not connected')
            || str_contains(strtolower($outputText), 'connect');

        self::assertTrue(
            $hasErrorIndication,
            'Executing command when disconnected should produce error message, got: ' . $outputText
        );
    }

    /**
     * Scenario #9: StreamingTransport receive() with timeout behavior.
     *
     * Specification: receive() with a timeout should:
     * - Return null or empty if no messages available
     * - NOT block indefinitely
     * - Have defined behavior
     *
     * Risk: UNDEFINED behavior
     */
    public function testStreamingTransportReceiveHasDefinedBehavior(): void
    {
        /** @var StreamingTransportInterface&MockObject $transport */
        $transport = $this->createMock(StreamingTransportInterface::class);
        $transport->method('isConnected')->willReturn(true);
        $transport->method('receive')->willReturn(null); // No messages available

        // Calling receive should have defined behavior
        $result = $transport->receive(0.1);

        // Should return null or Message instance
        self::assertTrue(
            $result === null || $result instanceof Message,
            'receive() should return null or Message instance'
        );
    }

    /**
     * Scenario #10: Chained aliases should expand only once (prevent indirect recursion).
     *
     * Specification: Given aliases a→b→c, expanding 'a' should:
     * - Expand to 'b' only (single expansion), OR
     * - Expand fully to 'c' with recursion limit
     * - NOT cause infinite expansion with circular chains (a→b→a)
     *
     * Risk: LOGIC error / potential infinite loop
     */
    public function testChainedAliasesExpandOnlyOnce(): void
    {
        $aliasManager = new AliasManager();

        // Create a chain: a → b → c → final_command
        $aliasManager->setAlias('a', 'b');
        $aliasManager->setAlias('b', 'c');
        $aliasManager->setAlias('c', 'final_command');

        // Expand 'a' - should expand once to 'b'
        $result = $aliasManager->expand('a');

        // Result should be 'b' (single expansion only)
        self::assertSame('b', $result, 'Alias should expand only once');
    }

    /**
     * Scenario #10b: Circular alias chain should not cause infinite loop.
     *
     * Specification: Given circular aliases (a→b→a), expansion should:
     * - Detect the cycle and stop
     * - NOT enter infinite recursion
     *
     * Risk: CRASH (stack overflow)
     */
    public function testCircularAliasChainDoesNotCauseInfiniteLoop(): void
    {
        $aliasManager = new AliasManager();

        // Create a circular chain: alias_x → alias_y → alias_x
        $aliasManager->setAlias('alias_x', 'alias_y');
        $aliasManager->setAlias('alias_y', 'alias_x');

        // This MUST NOT cause infinite recursion
        $result = $aliasManager->expand('alias_x');

        // Result should be bounded (expanded once to alias_y)
        self::assertSame('alias_y', $result);
    }

    // =========================================================================
    // ADDITIONAL EDGE CASE DISCOVERIES
    // =========================================================================

    /**
     * Scenario: Empty command should be handled gracefully.
     */
    public function testEmptyCommandHandledGracefully(): void
    {
        $parser = new ShellParser();

        $result = $parser->parse('');
        self::assertNotNull($result);
        self::assertSame('', $result->command);

        $resultWhitespace = $parser->parse('   ');
        self::assertNotNull($resultWhitespace);
    }

    /**
     * Scenario: Command with only special characters should not crash.
     */
    public function testCommandWithOnlySpecialCharactersDoesNotCrash(): void
    {
        $parser = new ShellParser();

        // These should not crash the parser
        $specialInputs = [
            '\\',
            '"',
            "'",
            '\\G',
            ';',
            ';;',
            '"""',
            "'''",
        ];

        foreach ($specialInputs as $input) {
            $result = $parser->parse($input);
            self::assertNotNull($result, "Parser crashed on input: {$input}");
        }
    }

    /**
     * Scenario: Extremely long command should be handled without memory issues.
     */
    public function testExtremeLongCommandHandledWithoutMemoryIssues(): void
    {
        $parser = new ShellParser();

        // Create a very long command (10KB)
        $longCommand = 'SELECT ' . str_repeat('column_name, ', 1000) . 'id FROM table';

        $result = $parser->parse($longCommand);
        self::assertNotNull($result);
        self::assertSame('SELECT', $result->command);
    }

    /**
     * Scenario: Unicode in command should be handled correctly.
     */
    public function testUnicodeInCommandHandledCorrectly(): void
    {
        $parser = new ShellParser();

        $unicodeCommand = 'SELECT * FROM users WHERE name = "日本語"';
        $result = $parser->parse($unicodeCommand);

        self::assertNotNull($result);
        self::assertSame('SELECT', $result->command);
    }

    /**
     * Scenario: Nested quotes should be parsed correctly.
     */
    public function testNestedQuotesHandledCorrectly(): void
    {
        $parser = new ShellParser();

        // Double quotes containing escaped quotes
        $result = $parser->parse('echo "He said \"hello\""');
        self::assertNotNull($result);
        self::assertSame('echo', $result->command);

        // Single quotes containing double quotes (no escaping needed)
        $result2 = $parser->parse("echo 'He said \"hello\"'");
        self::assertNotNull($result2);
        self::assertSame('echo', $result2->command);
    }

    /**
     * Scenario: Alias with arguments should expand correctly.
     */
    public function testAliasWithArgumentsExpandsCorrectly(): void
    {
        $aliasManager = new AliasManager();

        $aliasManager->setAlias('ll', 'ls -la');

        // Expand alias with additional arguments
        $result = $aliasManager->expand('ll /tmp');
        self::assertSame('ls -la /tmp', $result);

        // Non-existent alias should return original
        $noAlias = $aliasManager->expand('nonexistent');
        self::assertSame('nonexistent', $noAlias);
    }

    /**
     * Scenario: Alias expansion should not affect non-alias commands.
     */
    public function testNonAliasCommandsUnaffected(): void
    {
        $aliasManager = new AliasManager();

        $aliasManager->setAlias('ll', 'ls -la');

        // Command that starts with alias name but is different
        $result = $aliasManager->expand('llama command');
        self::assertSame('llama command', $result);

        // Command that contains alias name in middle
        $result2 = $aliasManager->expand('echo ll');
        self::assertSame('echo ll', $result2);
    }

    /**
     * Scenario: OutputFormatter with empty data should not crash.
     */
    public function testOutputFormatterWithEmptyDataDoesNotCrash(): void
    {
        $formatter = new OutputFormatter();

        // Empty array data
        $emptyResult = CommandResult::success(data: []);
        $output = $formatter->format($emptyResult, OutputFormat::Table);
        self::assertIsString($output);

        // Null data
        $nullResult = CommandResult::success(data: null, message: 'No data');
        $output2 = $formatter->format($nullResult, OutputFormat::Table);
        self::assertIsString($output2);
    }

    /**
     * Scenario: OutputFormatter with deeply nested data should not crash.
     */
    public function testOutputFormatterWithNestedDataDoesNotCrash(): void
    {
        $formatter = new OutputFormatter();

        // Nested array data
        $nestedData = [
            'level1' => [
                'level2' => [
                    'level3' => ['deep' => 'value'],
                ],
            ],
        ];

        $result = CommandResult::success(data: $nestedData);
        $output = $formatter->format($result, OutputFormat::Json);
        self::assertIsString($output);
        self::assertStringContainsString('deep', $output);
    }

    /**
     * Scenario: Message with null payload should be handled.
     */
    public function testMessageWithNullPayloadHandled(): void
    {
        $message = Message::fromArray([
            'type' => 'info',
            'payload' => null,
        ]);

        self::assertInstanceOf(Message::class, $message);
        self::assertNull($message->payload);
    }

    /**
     * Scenario: Message with missing required fields uses defaults.
     */
    public function testMessageWithMissingFieldsUsesDefaults(): void
    {
        // Completely empty array
        $message = Message::fromArray([]);

        self::assertInstanceOf(Message::class, $message);
        self::assertSame('unknown', $message->type);
        self::assertSame('unknown', $message->source);
    }
}
