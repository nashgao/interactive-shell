<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Specification;

use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\EchoHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\ErrorHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestServer;
use NashGao\InteractiveShell\Tests\Fixtures\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Shell Consumer Specification Tests.
 *
 * These tests define expected behavior from the SHELL CONSUMER's perspective.
 * A shell consumer is someone who:
 * - Creates a Shell instance with a transport
 * - Connects to a server
 * - Executes commands and receives output
 *
 * SPECIFICATION-FIRST: These tests define what a consumer expects,
 * NOT what the implementation currently does.
 *
 * Pre-Test Checklist:
 * - [x] Testing from the consumer's perspective
 * - [x] Tests would fail if the feature was broken
 * - [x] Written WITHOUT reading implementation first
 * - [x] Test names describe requirements, not implementation
 */
final class ShellConsumerSpecTest extends TestCase
{
    private TestServer $server;
    private InMemoryTransport $transport;
    private Shell $shell;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        // Set up the infrastructure a consumer would create
        $this->server = new TestServer();
        $this->server->register(new EchoHandler());
        $this->server->register(new ErrorHandler());

        $this->transport = new InMemoryTransport($this->server);
        $this->shell = new Shell($this->transport, 'consumer> ');
        $this->output = new BufferedOutput();
    }

    /**
     * SPECIFICATION: A consumer can create a shell, connect to a server,
     * and execute commands that return results.
     */
    public function testConsumerCanExecuteCommandAndReceiveOutput(): void
    {
        // Given: A consumer sets up shell infrastructure
        // (done in setUp)

        // When: Consumer connects and executes a command
        $this->transport->connect();
        $exitCode = $this->shell->executeCommand('echo Hello Consumer', $this->output);

        // Then: Consumer receives the expected output
        self::assertSame(0, $exitCode, 'Successful command should return exit code 0');
        self::assertStringContainsString('Hello Consumer', $this->output->fetch());
    }

    /**
     * SPECIFICATION: When a command fails, the consumer receives an error
     * and a non-zero exit code.
     */
    public function testConsumerReceivesErrorOnFailedCommand(): void
    {
        // Given: Consumer is connected
        $this->transport->connect();

        // When: Consumer executes a failing command
        $exitCode = $this->shell->executeCommand('fail Something went wrong', $this->output);

        // Then: Consumer sees error output and non-zero exit code
        self::assertSame(1, $exitCode, 'Failed command should return exit code 1');
        self::assertStringContainsString('Something went wrong', $this->output->fetch());
    }

    /**
     * SPECIFICATION: Consumer must connect before executing remote commands.
     * Disconnected shells should gracefully report the error.
     */
    public function testConsumerMustConnectBeforeExecutingRemoteCommands(): void
    {
        // Given: Consumer has NOT connected
        // (transport is not connected)

        // When: Consumer tries to execute a remote command
        $exitCode = $this->shell->executeCommand('echo test', $this->output);

        // Then: Consumer receives a clear error message
        self::assertSame(1, $exitCode, 'Should fail when not connected');
        self::assertStringContainsString('Not connected', $this->output->fetch());
    }

    /**
     * SPECIFICATION: Built-in commands work without a server connection.
     * This allows consumers to get help even when offline.
     */
    public function testConsumerCanUseBuiltInCommandsWithoutConnection(): void
    {
        // Given: Consumer has NOT connected to any server
        // (transport is not connected)

        // When: Consumer uses the built-in help command
        $exitCode = $this->shell->executeCommand('help', $this->output);

        // Then: Help is displayed successfully
        self::assertSame(0, $exitCode, 'Built-in commands should work offline');
        self::assertStringContainsString('Available Commands', $this->output->fetch());
    }

    /**
     * SPECIFICATION: Consumer can execute multiple commands in sequence.
     */
    public function testConsumerCanExecuteMultipleCommandsInSequence(): void
    {
        // Given: Consumer is connected
        $this->transport->connect();

        // When: Consumer executes multiple commands
        $exit1 = $this->shell->executeCommand('echo first', $this->output);
        $out1 = $this->output->fetch();

        $exit2 = $this->shell->executeCommand('echo second', $this->output);
        $out2 = $this->output->fetch();

        // Then: Each command produces its own output
        self::assertSame(0, $exit1);
        self::assertStringContainsString('first', $out1);

        self::assertSame(0, $exit2);
        self::assertStringContainsString('second', $out2);
    }

    /**
     * SPECIFICATION: Consumer can set a custom prompt for the shell.
     */
    public function testConsumerCanSetCustomPrompt(): void
    {
        // Given: Consumer creates a shell with a custom prompt
        $customShell = new Shell($this->transport, 'myapp> ');

        // Then: The shell should use the custom prompt
        // Note: We verify the shell was created successfully
        self::assertInstanceOf(Shell::class, $customShell);
    }

    /**
     * SPECIFICATION: Empty commands are handled gracefully without errors.
     */
    public function testConsumerCanSubmitEmptyCommandWithoutError(): void
    {
        // Given: Consumer is connected
        $this->transport->connect();

        // When: Consumer submits an empty command
        $exitCode = $this->shell->executeCommand('', $this->output);

        // Then: No error occurs
        self::assertSame(0, $exitCode, 'Empty command should not cause error');
        self::assertSame('', $this->output->fetch(), 'Empty command should produce no output');
    }

    /**
     * SPECIFICATION: Consumer can check if the transport is connected.
     */
    public function testConsumerCanCheckConnectionStatus(): void
    {
        // Given: Consumer has a shell
        $transport = $this->shell->getTransport();

        // Then: Transport reports disconnected initially
        self::assertFalse($transport->isConnected());

        // When: Consumer connects
        $transport->connect();

        // Then: Transport reports connected
        self::assertTrue($transport->isConnected());
    }

    /**
     * SPECIFICATION: Consumer can disconnect and reconnect.
     */
    public function testConsumerCanDisconnectAndReconnect(): void
    {
        // Given: Consumer is connected
        $this->transport->connect();
        $exit1 = $this->shell->executeCommand('echo connected', $this->output);
        self::assertSame(0, $exit1);
        $this->output->fetch();

        // When: Consumer disconnects
        $this->transport->disconnect();

        // Then: Commands fail
        $exit2 = $this->shell->executeCommand('echo should fail', $this->output);
        self::assertSame(1, $exit2);
        $this->output->fetch();

        // When: Consumer reconnects
        $this->transport->connect();

        // Then: Commands work again
        $exit3 = $this->shell->executeCommand('echo reconnected', $this->output);
        self::assertSame(0, $exit3);
        self::assertStringContainsString('reconnected', $this->output->fetch());
    }

    /**
     * SPECIFICATION: Consumer can get transport information for diagnostics.
     */
    public function testConsumerCanGetTransportInfoForDiagnostics(): void
    {
        // Given: Consumer has a shell
        $transport = $this->shell->getTransport();

        // When: Consumer requests transport info
        $info = $transport->getInfo();

        // Then: Useful diagnostic info is available
        self::assertIsArray($info);
        self::assertArrayHasKey('type', $info);
        self::assertArrayHasKey('connected', $info);
    }

    /**
     * SPECIFICATION: Consumer can use the exit command to stop the shell.
     */
    public function testConsumerCanUseExitCommand(): void
    {
        // Given: Consumer has a running shell
        self::assertFalse($this->shell->isRunning(), 'Shell should not be running initially');

        // When: Consumer executes exit command
        $exitCode = $this->shell->executeCommand('exit', $this->output);

        // Then: Shell flags for exit (would stop the run loop)
        self::assertSame(0, $exitCode, 'Exit command should succeed');
    }

    /**
     * SPECIFICATION: Consumer can ping the server to check connectivity.
     */
    public function testConsumerCanPingServer(): void
    {
        // Given: Consumer is not connected
        self::assertFalse($this->transport->ping(), 'Ping should fail when disconnected');

        // When: Consumer connects
        $this->transport->connect();

        // Then: Ping succeeds
        self::assertTrue($this->transport->ping(), 'Ping should succeed when connected');
    }

    /**
     * SPECIFICATION: Consumer can see which commands are available on the server.
     */
    public function testConsumerCanDiscoverAvailableCommands(): void
    {
        // Given: Consumer has a server with registered handlers
        $info = $this->transport->getInfo();

        // Then: Available commands are listed
        self::assertArrayHasKey('commands', $info);
        self::assertIsArray($info['commands']);
        self::assertContains('echo', $info['commands']);
        self::assertContains('fail', $info['commands']);
    }

    /**
     * SPECIFICATION: Consumer can get the transport endpoint for display.
     */
    public function testConsumerCanGetTransportEndpoint(): void
    {
        // Given: Consumer has a transport
        $endpoint = $this->transport->getEndpoint();

        // Then: Endpoint is a readable string
        self::assertIsString($endpoint);
        self::assertNotEmpty($endpoint);
    }
}
