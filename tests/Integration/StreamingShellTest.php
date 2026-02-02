<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration;

use NashGao\InteractiveShell\Message\FilterExpression;
use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\StreamingShell;
use NashGao\InteractiveShell\Transport\StreamingTransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(StreamingShell::class)]
final class StreamingShellTest extends TestCase
{
    private StreamingTransportInterface&MockObject $mockTransport;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->mockTransport = $this->createMock(StreamingTransportInterface::class);
        $this->output = new BufferedOutput();
    }

    public function testConstructorInitializesComponents(): void
    {
        $shell = new StreamingShell($this->mockTransport, 'stream> ');

        self::assertNotNull($shell->getOutputFormatter());
        self::assertSame(0, $shell->getMessageCount());
    }

    public function testIsRunningReturnsFalseInitially(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        self::assertFalse($shell->isRunning());
    }

    public function testStopSetsRunningToFalse(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $shell->stop();

        self::assertFalse($shell->isRunning());
    }

    public function testSetFilter(): void
    {
        $shell = new StreamingShell($this->mockTransport);
        $filter = FilterExpression::parse('type:data');

        $shell->setFilter($filter);

        // Filter is private, but setFilter should work without throwing
        self::assertFalse($shell->isRunning());
    }

    public function testExecuteExitCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        // Execute exit command
        $shell->executeCommand('exit', $this->output);

        // After exit command, shell should stop running
        self::assertFalse($shell->isRunning());
    }

    public function testExecuteQuitCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $shell->executeCommand('quit', $this->output);

        self::assertFalse($shell->isRunning());
    }

    public function testExecutePauseCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $exitCode = $shell->executeCommand('pause', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('paused', strtolower($outputContent));
    }

    public function testExecuteResumeCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        // First pause
        $shell->executeCommand('pause', new BufferedOutput());

        // Then resume
        $exitCode = $shell->executeCommand('resume', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('resumed', strtolower($outputContent));
    }

    public function testExecuteStatsCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $exitCode = $shell->executeCommand('stats', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('Messages received:', $outputContent);
        self::assertStringContainsString('Filter:', $outputContent);
        self::assertStringContainsString('Paused:', $outputContent);
    }

    public function testExecuteFilterShowCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $exitCode = $shell->executeCommand('filter show', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('Current filter:', $outputContent);
    }

    public function testExecuteFilterSetCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $exitCode = $shell->executeCommand('filter topic:sensors/*', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('Filter set:', $outputContent);
        self::assertStringContainsString('topic:sensors/*', $outputContent);
    }

    public function testExecuteFilterClearCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        // First set a filter
        $shell->executeCommand('filter topic:test', new BufferedOutput());

        // Then clear it
        $exitCode = $shell->executeCommand('filter clear', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('cleared', strtolower($outputContent));
    }

    public function testExecuteFilterNoneCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $exitCode = $shell->executeCommand('filter none', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('cleared', strtolower($outputContent));
    }

    public function testExecuteHelpCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $exitCode = $shell->executeCommand('help', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('Available Commands', $outputContent);
    }

    public function testExecuteRemoteCommand(): void
    {
        $this->mockTransport->expects(self::once())
            ->method('sendAsync')
            ->with(self::callback(function ($cmd) {
                return $cmd->command === 'subscribe';
            }));

        $shell = new StreamingShell($this->mockTransport);

        $exitCode = $shell->executeCommand('subscribe', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('Command sent:', $outputContent);
    }

    public function testExecuteEmptyCommand(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $exitCode = $shell->executeCommand('', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertSame('', $outputContent);
    }

    public function testDefaultAliases(): void
    {
        $shell = new StreamingShell($this->mockTransport, 'stream> ', ['p' => 'pause']);

        // Execute alias
        $exitCode = $shell->executeCommand('p', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('paused', strtolower($outputContent));
    }

    public function testExecuteMultipleFilterTerms(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $exitCode = $shell->executeCommand('filter topic:sensors/* type:data', $this->output);

        self::assertSame(0, $exitCode);

        $outputContent = $this->output->fetch();
        self::assertStringContainsString('topic:sensors/*', $outputContent);
        self::assertStringContainsString('type:data', $outputContent);
    }

    public function testGetMessageCountInitiallyZero(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        self::assertSame(0, $shell->getMessageCount());
    }

    public function testGetOutputFormatter(): void
    {
        $shell = new StreamingShell($this->mockTransport);

        $formatter = $shell->getOutputFormatter();

        self::assertNotNull($formatter);
    }
}
