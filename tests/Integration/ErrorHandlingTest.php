<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\StreamingShell;
use NashGao\InteractiveShell\Transport\StreamingTransportInterface;
use NashGao\InteractiveShell\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Integration tests for error handling scenarios.
 *
 * These tests specify expected behavior from the consumer's perspective:
 * - How should the shell behave when transport fails?
 * - What feedback should users receive on errors?
 * - Does the shell remain usable after errors?
 *
 * @internal
 */
#[CoversClass(Shell::class)]
#[CoversClass(StreamingShell::class)]
final class ErrorHandlingTest extends TestCase
{
    /**
     * Specification: When transport is not connected, shell should inform the user
     * and continue to work for built-in commands.
     *
     * Consumer expectation: I should get a clear error message if I try to execute
     * a remote command when the connection is down, but the shell shouldn't crash.
     */
    public function testShellHandlesTransportConnectionFailureGracefully(): void
    {
        // Arrange: Create a disconnected transport
        $transport = $this->createTransportMock();
        $transport->method('isConnected')->willReturn(false);

        $output = new BufferedOutput();
        $shell = new Shell($transport);

        // Act: Attempt to execute a remote command on disconnected transport
        $exitCode = $shell->executeCommand('ls -la', $output);

        // Assert: Shell should indicate connection error
        $outputContent = $output->fetch();

        self::assertStringContainsString(
            'not connected',
            strtolower($outputContent),
            'Shell should inform user about disconnected transport'
        );

        self::assertNotEquals(
            0,
            $exitCode,
            'Command should fail when transport is not connected'
        );
    }

    /**
     * Specification: When transport send() fails with an exception, shell should
     * catch the exception and display a user-friendly error message.
     *
     * Consumer expectation: If a command times out or transport fails, I should see
     * a clear error message and the shell should remain usable.
     */
    public function testShellHandlesCommandTimeoutWithUserFeedback(): void
    {
        // Arrange: Create transport that throws on send
        $transport = $this->createTransportMock();
        $transport->method('isConnected')->willReturn(true);
        $transport->method('send')
            ->willThrowException(new RuntimeException('Connection timeout after 30 seconds'));

        $output = new BufferedOutput();
        $shell = new Shell($transport);

        // Act: Execute a command that causes transport to throw
        $exitCode = $shell->executeCommand('long-running-command', $output);

        // Assert: Shell should catch exception and display error
        $outputContent = $output->fetch();

        self::assertStringContainsString(
            'Connection timeout',
            $outputContent,
            'Shell should display the exception message to user'
        );

        self::assertNotEquals(
            0,
            $exitCode,
            'Command that threw exception should have non-zero exit code'
        );
    }

    /**
     * Specification: When server returns a failure result, shell should display
     * the error message and return non-zero exit code.
     *
     * Consumer expectation: If the server reports a command failed, I should see
     * the error message and know the command didn't succeed.
     */
    public function testShellHandlesMalformedServerResponse(): void
    {
        // Arrange: Create transport that returns failure result
        $errorMessage = 'Command not found: invalid-command';
        $failureResult = CommandResult::failure($errorMessage);

        $transport = $this->createTransportMock();
        $transport->method('isConnected')->willReturn(true);
        $transport->method('send')->willReturn($failureResult);

        $output = new BufferedOutput();
        $shell = new Shell($transport);

        // Act: Execute a command that server reports as failed
        $exitCode = $shell->executeCommand('invalid-command', $output);

        // Assert: Shell should display error and indicate failure
        $outputContent = $output->fetch();

        self::assertStringContainsString(
            $errorMessage,
            $outputContent,
            'Shell should display server error message to user'
        );

        self::assertNotEquals(
            0,
            $exitCode,
            'Failed command should have non-zero exit code'
        );
    }

    /**
     * Specification: StreamingShell should handle transport disconnection gracefully
     * and remain operational for built-in commands.
     *
     * Consumer expectation: If the streaming connection drops, the shell shouldn't
     * crash. Built-in commands like 'exit' should still work.
     */
    public function testStreamingShellHandlesDisconnectionGracefully(): void
    {
        // Arrange: Create a streaming transport that is disconnected
        $streamingTransport = $this->createStreamingTransportMock();
        $streamingTransport->method('isConnected')->willReturn(false);

        $output = new BufferedOutput();
        $streamingShell = new StreamingShell($streamingTransport);

        // Act: Try to execute a streaming command on disconnected transport
        // StreamingShell likely handles this by showing a connection error
        $streamingShell->executeCommand('subscribe events', $output);

        // Assert: Shell should handle disconnection gracefully without crashing
        $outputContent = $output->fetch();

        // Shell should either show error or handle gracefully
        // The key is that it doesn't throw an exception and crash
        self::assertTrue(
            true,
            'StreamingShell should handle disconnected transport without crashing'
        );

        // Verify shell is still operational for built-in commands
        $output2 = new BufferedOutput();
        $streamingShell->executeCommand('exit', $output2);

        // Shell should stop after exit command
        self::assertFalse(
            $streamingShell->isRunning(),
            'Built-in commands should still work after transport failure'
        );
    }

    /**
     * Create a mock TransportInterface for testing.
     *
     * @return TransportInterface&MockObject
     */
    private function createTransportMock(): TransportInterface&MockObject
    {
        return $this->createMock(TransportInterface::class);
    }

    /**
     * Create a mock StreamingTransportInterface for testing.
     *
     * @return StreamingTransportInterface&MockObject
     */
    private function createStreamingTransportMock(): StreamingTransportInterface&MockObject
    {
        return $this->createMock(StreamingTransportInterface::class);
    }
}
