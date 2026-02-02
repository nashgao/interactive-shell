<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration;

use NashGao\InteractiveShell\Message\FilterExpression;
use NashGao\InteractiveShell\StreamingShell;
use NashGao\InteractiveShell\Transport\StreamingTransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * StreamingShell Consumer Lifecycle Specifications
 *
 * These tests specify expected behavior from the consumer's perspective.
 * They define what SHOULD happen when a consumer uses StreamingShell,
 * testing the command interface and observable state changes.
 *
 * Note: These tests focus on the command execution interface rather than
 * the internal streaming loop, which requires async runtime (Swoole).
 *
 * @internal
 */
#[CoversClass(StreamingShell::class)]
final class StreamingShellLifecycleTest extends TestCase
{
    private StreamingTransportInterface&MockObject $transport;
    private BufferedOutput $output;
    private StreamingShell $shell;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = self::createMock(StreamingTransportInterface::class);
        $this->output = new BufferedOutput();

        // StreamingShell constructor: (transport, prompt, aliases)
        $this->shell = new StreamingShell($this->transport, 'test> ');
    }

    /**
     * Specification: When a consumer starts using StreamingShell, the shell should
     * be properly initialized with message tracking capabilities and ready to accept commands.
     *
     * Expected behavior:
     * - Message count starts at zero
     * - Shell can track stats about message processing
     * - Stats command provides visibility into current state
     */
    public function testConsumerStartsStreamingAndReceivesMessages(): void
    {
        // Specification: Initially, no messages have been received
        self::assertSame(0, $this->shell->getMessageCount(),
            'Message count should be zero before any streaming activity');

        // Specification: Consumer can query streaming statistics at any time
        $exitCode = $this->shell->executeCommand('stats', $this->output);

        self::assertSame(0, $exitCode, 'Stats command should execute successfully');

        // Specification: Stats output includes message count and state information
        $output = $this->output->fetch();
        self::assertStringContainsString('Messages received:', $output,
            'Stats should show message count');
        self::assertStringContainsString('Filter:', $output,
            'Stats should show current filter state');
        self::assertStringContainsString('Paused:', $output,
            'Stats should show pause state');
    }

    /**
     * Specification: When a consumer sets a message filter, the shell should
     * acknowledge the filter and make it queryable.
     *
     * Expected behavior:
     * - Filters can be set programmatically via setFilter()
     * - Filters can be set via command line using 'filter' command
     * - Filter state can be queried using 'filter show' command
     * - Filter can be cleared using 'filter clear' command
     */
    public function testConsumerFiltersMessagesWhileStreaming(): void
    {
        // Specification: Consumer can set filter programmatically
        $filter = FilterExpression::parse('content:important');
        $this->shell->setFilter($filter);

        // Specification: Filter show command displays current filter
        $this->shell->executeCommand('filter show', $this->output);
        $showOutput = $this->output->fetch();
        self::assertStringContainsString('Current filter:', $showOutput,
            'Filter show should display current filter state');

        // Specification: Consumer can also set filters via command interface
        $this->shell->executeCommand('filter content:contains:urgent', $this->output);
        $setOutput = $this->output->fetch();
        self::assertStringContainsString('Filter set:', $setOutput,
            'Filter command should confirm filter was set');
        self::assertStringContainsString('urgent', $setOutput,
            'Filter output should show the filter criteria');

        // Specification: Consumer can clear filters
        $this->shell->executeCommand('filter clear', $this->output);
        $clearOutput = $this->output->fetch();
        self::assertTrue(
            str_contains(strtolower($clearOutput), 'cleared') ||
            str_contains(strtolower($clearOutput), 'showing all'),
            'Filter clear should confirm filter was removed'
        );
    }

    /**
     * Specification: When a consumer pauses streaming, message display should stop.
     * When resumed, message display should continue.
     *
     * Expected behavior:
     * - Pause command provides clear confirmation
     * - Resume command provides clear confirmation
     * - Commands return success exit code
     * - State changes are reflected in stats output
     */
    public function testConsumerPausesAndResumesStreamWithoutLoss(): void
    {
        // Specification: Pause command suspends message display
        $pauseExitCode = $this->shell->executeCommand('pause', $this->output);
        $pauseOutput = $this->output->fetch();

        self::assertSame(0, $pauseExitCode, 'Pause command should succeed');
        self::assertStringContainsString('paused', strtolower($pauseOutput),
            'Pause command should confirm streaming is paused');

        // Specification: Stats reflects paused state
        $this->shell->executeCommand('stats', $this->output);
        $statsOutput = $this->output->fetch();
        self::assertStringContainsString('Paused: Yes', $statsOutput,
            'Stats should show paused state as Yes');

        // Specification: Resume command restarts message display
        $resumeExitCode = $this->shell->executeCommand('resume', $this->output);
        $resumeOutput = $this->output->fetch();

        self::assertSame(0, $resumeExitCode, 'Resume command should succeed');
        self::assertStringContainsString('resumed', strtolower($resumeOutput),
            'Resume command should confirm streaming has resumed');

        // Specification: Stats reflects resumed (not paused) state
        $this->shell->executeCommand('stats', $this->output);
        $statsAfterResume = $this->output->fetch();
        self::assertStringContainsString('Paused: No', $statsAfterResume,
            'Stats should show paused state as No after resume');
    }

    /**
     * Specification: When a consumer sends a command that is not a built-in,
     * the command should be transmitted to the server asynchronously.
     *
     * Expected behavior:
     * - Non-built-in commands are sent via transport's sendAsync
     * - Consumer receives confirmation that command was sent
     * - Command execution returns success
     */
    public function testConsumerSendsAsyncCommandWhileReceiving(): void
    {
        $customCommand = 'subscribe';

        // Specification: Custom commands should be sent via transport's async mechanism
        $this->transport
            ->expects(self::once())
            ->method('sendAsync')
            ->with(self::callback(function ($parsed) use ($customCommand): bool {
                // Verify the ParsedCommand has the correct command name
                return $parsed->command === $customCommand;
            }));

        // Consumer expectation: Execute custom command
        $exitCode = $this->shell->executeCommand($customCommand, $this->output);

        self::assertSame(0, $exitCode, 'Custom command should execute successfully');

        // Specification: Consumer should receive feedback that command was sent
        $output = $this->output->fetch();
        self::assertStringContainsString('Command sent:', $output,
            'Output should confirm command was sent to server');
        self::assertStringContainsString($customCommand, $output,
            'Output should include the command name');
    }
}
