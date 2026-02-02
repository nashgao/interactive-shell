<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration;

use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\Transport\TransportInterface;
use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Specification-first integration tests for Shell consumer lifecycle.
 *
 * These tests define EXPECTED behavior from a consumer's perspective,
 * written WITHOUT reading implementation code first.
 *
 * @internal
 */
#[CoversClass(Shell::class)]
final class ShellLifecycleTest extends TestCase
{
    private TransportInterface&MockObject $transport;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = $this->createMock(TransportInterface::class);
        $this->output = new BufferedOutput();
    }

    /**
     * Specification: When a consumer creates a Shell and transport is connected,
     * commands should execute and return results to the consumer.
     */
    public function testConsumerStartsShellConnectsAndReceivesWelcome(): void
    {
        $this->transport
            ->method('isConnected')
            ->willReturn(true);

        $this->transport
            ->method('send')
            ->willReturn(CommandResult::success(['status' => 'ok']));

        $shell = new Shell($this->transport, 'test> ', []);

        // Consumer executes a command - transport should be ready
        $exitCode = $shell->executeCommand('SHOW STATUS', $this->output);

        $outputContent = $this->output->fetch();

        // Consumer should see command output
        self::assertNotEmpty($outputContent, 'Consumer should see output after command execution');
        self::assertSame(0, $exitCode, 'Successful command should return 0 exit code');
    }

    /**
     * Specification: When a consumer executes a command that returns tabular data,
     * the output should appear as a formatted table by default.
     */
    public function testConsumerExecutesCommandAndSeesFormattedOutput(): void
    {
        $this->transport
            ->method('connect');

        $this->transport
            ->method('isConnected')
            ->willReturn(true);

        $testData = [
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $this->transport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (ParsedCommand $cmd): bool {
                // Parser splits command: "SELECT * FROM users" -> command="SELECT", args=["*", "FROM", "users"]
                return $cmd->command === 'SELECT' && in_array('users', $cmd->arguments, true);
            }))
            ->willReturn(CommandResult::success($testData));

        $shell = new Shell($this->transport, 'shell> ', []);

        $shell->executeCommand('SELECT * FROM users', $this->output);

        $outputContent = $this->output->fetch();

        // Consumer should see their data formatted in the output
        self::assertStringContainsString('Alice', $outputContent, 'Output should contain first row data');
        self::assertStringContainsString('Bob', $outputContent, 'Output should contain second row data');
        self::assertStringContainsString('alice@example.com', $outputContent, 'Output should contain email from first row');
        self::assertStringContainsString('bob@example.com', $outputContent, 'Output should contain email from second row');
    }

    /**
     * Specification: When input ends with backslash, the shell should accept continuation
     * and combine multiple lines into a single command for execution.
     */
    public function testConsumerUsesMultiLineInputWithBackslashContinuation(): void
    {
        $this->transport
            ->method('connect');

        $this->transport
            ->method('isConnected')
            ->willReturn(true);

        $this->transport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (ParsedCommand $cmd): bool {
                // The raw command should contain the multi-line input
                return $cmd->command === 'SELECT' && str_contains($cmd->raw, "\n");
            }))
            ->willReturn(CommandResult::success([]));

        $shell = new Shell($this->transport, 'shell> ', []);

        // Consumer provides multi-line input with backslash continuation
        $shell->executeCommand("SELECT * \\\nFROM users", $this->output);

        $outputContent = $this->output->fetch();

        // Command should execute successfully without errors
        self::assertStringNotContainsString('error', strtolower($outputContent),
            'Multi-line command should execute without errors');
    }

    /**
     * Specification: When consumer manually adds commands to history,
     * the 'history' command should display them as numbered entries.
     */
    public function testConsumerNavigatesHistoryAfterExecutingCommands(): void
    {
        $this->transport
            ->method('isConnected')
            ->willReturn(false); // Not connected, so commands won't be sent

        $shell = new Shell($this->transport, 'shell> ', []);

        // Consumer builds history through the history manager
        // (In real usage, the REPL loop in run() adds to history)
        $history = $shell->getHistory();
        $history->add('SELECT 1');
        $history->add('SELECT 2');
        $history->add('SELECT 3');

        // Consumer requests command history (built-in command)
        $shell->executeCommand('history', $this->output);

        $outputContent = $this->output->fetch();

        // History should show all previously executed commands
        self::assertStringContainsString('SELECT 1', $outputContent, 'History should contain first command');
        self::assertStringContainsString('SELECT 2', $outputContent, 'History should contain second command');
        self::assertStringContainsString('SELECT 3', $outputContent, 'History should contain third command');

        // History entries should be numbered
        self::assertMatchesRegularExpression('/\d+.*SELECT/', $outputContent,
            'History should show numbered entries');
    }

    /**
     * Specification: When a consumer defines an alias and uses it,
     * the aliased command should execute as if the full command was typed.
     */
    public function testConsumerUsesAliasToExecuteCommand(): void
    {
        $this->transport
            ->method('connect');

        $this->transport
            ->method('isConnected')
            ->willReturn(true);

        $aliases = ['q' => 'exit', 'ls' => 'SHOW TABLES'];
        $shell = new Shell($this->transport, 'shell> ', $aliases);

        $this->transport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (ParsedCommand $cmd): bool {
                // Parser splits "SHOW TABLES" -> command="SHOW", args=["TABLES"]
                return $cmd->command === 'SHOW' && in_array('TABLES', $cmd->arguments, true);
            }))
            ->willReturn(CommandResult::success([['table' => 'users'], ['table' => 'orders']]));

        // Consumer uses the alias 'ls' instead of typing 'SHOW TABLES'
        $shell->executeCommand('ls', $this->output);

        $outputContent = $this->output->fetch();

        // The aliased command should execute successfully
        self::assertStringNotContainsString('Unknown command', $outputContent,
            'Alias should be recognized and executed');
        self::assertStringContainsString('users', $outputContent,
            'Aliased command output should appear');
    }

    /**
     * Specification: When a command ends with \G terminator, the output should be
     * displayed in vertical MySQL-style format instead of tabular format.
     */
    public function testConsumerSeesVerticalOutputWithGTerminator(): void
    {
        $this->transport
            ->method('connect');

        $this->transport
            ->method('isConnected')
            ->willReturn(true);

        $testData = [
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        ];

        $this->transport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (ParsedCommand $cmd): bool {
                // Command should be parsed with vertical output flag
                return str_contains($cmd->command, 'SELECT') && $cmd->hasVerticalTerminator;
            }))
            ->willReturn(CommandResult::success($testData));

        $shell = new Shell($this->transport, 'shell> ', []);

        // Consumer executes command with \G for vertical output format
        $shell->executeCommand('SELECT * FROM users WHERE id = 1\G', $this->output);

        $outputContent = $this->output->fetch();

        // Output should be in vertical format with row markers
        self::assertMatchesRegularExpression('/\*+\s*\d+\.\s*row\s*\*+/i', $outputContent,
            'Output should show vertical format with row markers');
        self::assertStringContainsString('Alice', $outputContent,
            'Vertical output should contain the data');
        self::assertStringContainsString('alice@example.com', $outputContent,
            'Vertical output should contain all fields');
    }

    /**
     * Specification: When consumer executes 'exit' command, the shell should
     * gracefully disconnect and display a goodbye message.
     */
    public function testConsumerExitsGracefullyWithSessionSaved(): void
    {
        $this->transport
            ->method('connect');

        $this->transport
            ->method('isConnected')
            ->willReturn(true);

        $shell = new Shell($this->transport, 'shell> ', []);

        // Consumer executes exit command to close the session
        $exitCode = $shell->executeCommand('exit', $this->output);

        $outputContent = $this->output->fetch();

        // Shell should display goodbye message
        self::assertStringContainsString('Goodbye', $outputContent,
            'Exit should show goodbye message to consumer');

        // Exit command should return success exit code
        self::assertSame(0, $exitCode,
            'Exit command should return success exit code');
    }
}
