<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Formatter\OutputFormat;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(Shell::class)]
final class ShellTest extends TestCase
{
    private TransportInterface&MockObject $mockTransport;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->mockTransport = $this->createMock(TransportInterface::class);
        $this->output = new BufferedOutput();
    }

    private function createTestHistoryFile(): string
    {
        return sys_get_temp_dir() . '/test_shell_history_' . uniqid();
    }

    public function testConstructorInitializesComponents(): void
    {
        $shell = new Shell($this->mockTransport, 'test> ', [], $this->createTestHistoryFile());

        self::assertSame($this->mockTransport, $shell->getTransport());
        self::assertNotNull($shell->getAliases());
        self::assertNotNull($shell->getHistory());
    }

    public function testIsRunningReturnsFalseInitially(): void
    {
        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        self::assertFalse($shell->isRunning());
    }

    public function testStopSetsRunningToFalse(): void
    {
        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $shell->stop();

        self::assertFalse($shell->isRunning());
    }

    public function testExecuteHelpCommand(): void
    {
        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $exitCode = $shell->executeCommand('help', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('Available Commands', $outputContent);
    }

    public function testExecuteStatusCommand(): void
    {
        $this->mockTransport->method('isConnected')->willReturn(false);
        $this->mockTransport->method('getEndpoint')->willReturn('http://localhost:9501');

        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $exitCode = $shell->executeCommand('status', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        // Status shows "connected: no" for disconnected state
        self::assertStringContainsString('connected: no', strtolower($outputContent));
    }

    public function testExecuteRemoteCommand(): void
    {
        $this->mockTransport->method('isConnected')->willReturn(true);
        $this->mockTransport->expects(self::once())
            ->method('send')
            ->with(self::callback(function (ParsedCommand $cmd) {
                return $cmd->command === 'pool:list';
            }))
            ->willReturn(CommandResult::success(
                [
                    ['name' => 'default', 'connections' => 5],
                    ['name' => 'redis', 'connections' => 3],
                ]
            ));

        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $exitCode = $shell->executeCommand('pool:list', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('default', $outputContent);
        self::assertStringContainsString('redis', $outputContent);
    }

    public function testExecuteRemoteCommandWithJsonFormat(): void
    {
        $this->mockTransport->method('isConnected')->willReturn(true);
        $this->mockTransport->expects(self::once())
            ->method('send')
            ->willReturn(CommandResult::success(['key' => 'value']));

        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $exitCode = $shell->executeCommand('test --format=json', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('"key"', $outputContent);
        self::assertStringContainsString('"value"', $outputContent);
    }

    public function testExecuteRemoteCommandWithVerticalTerminator(): void
    {
        $this->mockTransport->method('isConnected')->willReturn(true);
        $this->mockTransport->expects(self::once())
            ->method('send')
            ->willReturn(CommandResult::success([
                ['id' => 1, 'name' => 'Test'],
            ]));

        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $exitCode = $shell->executeCommand('test\\G', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('row', $outputContent);
        self::assertStringContainsString('id:', $outputContent);
        self::assertStringContainsString('name:', $outputContent);
    }

    public function testExecuteCommandWhenNotConnected(): void
    {
        $this->mockTransport->method('isConnected')->willReturn(false);

        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $exitCode = $shell->executeCommand('remote:command', $this->output);

        self::assertSame(1, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('Not connected', $outputContent);
    }

    public function testExecuteFailedRemoteCommand(): void
    {
        $this->mockTransport->method('isConnected')->willReturn(true);
        $this->mockTransport->expects(self::once())
            ->method('send')
            ->willReturn(CommandResult::failure('Command not found'));

        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $exitCode = $shell->executeCommand('unknown', $this->output);

        self::assertSame(1, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('Command not found', $outputContent);
    }

    public function testExecuteEmptyCommand(): void
    {
        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $exitCode = $shell->executeCommand('', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertSame('', $outputContent);
    }

    public function testSetPrompt(): void
    {
        $shell = new Shell($this->mockTransport, 'old> ', [], $this->createTestHistoryFile());

        $shell->setPrompt('new> ');

        // Prompt is private, but we can verify it doesn't throw
        self::assertFalse($shell->isRunning());
    }

    public function testSetOutputFormat(): void
    {
        $this->mockTransport->method('isConnected')->willReturn(true);
        $this->mockTransport->expects(self::once())
            ->method('send')
            ->willReturn(CommandResult::success(['key' => 'value']));

        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());
        $shell->setOutputFormat(OutputFormat::Json);

        // Without --format option, should use default JSON format
        $exitCode = $shell->executeCommand('test', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        // JSON format output
        self::assertStringContainsString('"key"', $outputContent);
    }

    public function testDefaultAliases(): void
    {
        $shell = new Shell($this->mockTransport, 'shell> ', ['q' => 'exit'], $this->createTestHistoryFile());

        $aliases = $shell->getAliases();

        self::assertTrue($aliases->hasAlias('q'));
    }

    public function testHistoryCommand(): void
    {
        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        // Execute history command on fresh shell
        $exitCode = $shell->executeCommand('history', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        // Fresh shell has no history
        self::assertStringContainsString('No command history', $outputContent);
    }

    public function testClearCommand(): void
    {
        $shell = new Shell($this->mockTransport, 'shell> ', [], $this->createTestHistoryFile());

        $exitCode = $shell->executeCommand('clear', $this->output);

        self::assertSame(0, $exitCode);

        // clear sends escape codes
        $outputContent = $this->output->fetch();
        self::assertNotEmpty($outputContent);
    }
}
