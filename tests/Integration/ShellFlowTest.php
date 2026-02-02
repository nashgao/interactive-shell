<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Integration;

use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\DelayHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\EchoHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\ErrorHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestContext;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestServer;
use NashGao\InteractiveShell\Tests\Fixtures\Transport\InMemoryTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Integration tests for Shell using InMemoryTransport.
 *
 * These tests exercise the full Shell → Transport → Server → Handler pipeline
 * without any network overhead, enabling fast and reliable tests.
 */
#[CoversClass(Shell::class)]
#[CoversClass(InMemoryTransport::class)]
final class ShellFlowTest extends TestCase
{
    private TestServer $server;
    private InMemoryTransport $transport;
    private Shell $shell;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->server = new TestServer();
        $this->server->register(new EchoHandler());
        $this->server->register(new ErrorHandler());
        $this->server->register(new DelayHandler());

        $this->transport = new InMemoryTransport($this->server);
        $this->shell = new Shell($this->transport, 'test> ');
        $this->output = new BufferedOutput();
    }

    public function testSuccessfulEchoCommand(): void
    {
        $this->transport->connect();

        $exitCode = $this->shell->executeCommand('echo hello world', $this->output);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('hello world', $this->output->fetch());
    }

    public function testEchoCommandWithNoArguments(): void
    {
        $this->transport->connect();

        $exitCode = $this->shell->executeCommand('echo', $this->output);

        self::assertSame(0, $exitCode);
    }

    public function testFailingCommand(): void
    {
        $this->transport->connect();

        $exitCode = $this->shell->executeCommand('fail custom error', $this->output);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('custom error', $this->output->fetch());
    }

    public function testFailingCommandWithDefaultMessage(): void
    {
        $this->transport->connect();

        $exitCode = $this->shell->executeCommand('fail', $this->output);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Intentional failure', $this->output->fetch());
    }

    public function testUnknownCommand(): void
    {
        $this->transport->connect();

        $exitCode = $this->shell->executeCommand('nonexistent', $this->output);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unknown command', $this->output->fetch());
    }

    public function testCommandWhenNotConnected(): void
    {
        // Don't call connect()
        $exitCode = $this->shell->executeCommand('echo test', $this->output);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Not connected', $this->output->fetch());
    }

    public function testDelayCommand(): void
    {
        $this->transport->connect();

        $start = microtime(true);
        $exitCode = $this->shell->executeCommand('delay 0.01', $this->output);
        $duration = microtime(true) - $start;

        self::assertSame(0, $exitCode);
        self::assertGreaterThanOrEqual(0.01, $duration);
        self::assertStringContainsString('Delayed', $this->output->fetch());
    }

    public function testBuiltInHelpCommand(): void
    {
        // Built-in commands work without connection
        $exitCode = $this->shell->executeCommand('help', $this->output);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Available Commands', $this->output->fetch());
    }

    public function testBuiltInExitCommand(): void
    {
        $exitCode = $this->shell->executeCommand('exit', $this->output);

        self::assertSame(0, $exitCode);
        self::assertFalse($this->shell->isRunning());
    }

    public function testEmptyCommand(): void
    {
        $exitCode = $this->shell->executeCommand('', $this->output);

        self::assertSame(0, $exitCode);
        self::assertSame('', $this->output->fetch());
    }

    public function testTransportInfo(): void
    {
        $info = $this->transport->getInfo();

        self::assertSame('in-memory', $info['type']);
        self::assertTrue($info['test']);
        self::assertIsArray($info['commands']);
        self::assertContains('echo', $info['commands']);
        self::assertContains('fail', $info['commands']);
    }

    public function testTransportPing(): void
    {
        self::assertFalse($this->transport->ping());

        $this->transport->connect();

        self::assertTrue($this->transport->ping());

        $this->transport->disconnect();

        self::assertFalse($this->transport->ping());
    }

    public function testTransportEndpoint(): void
    {
        self::assertSame('memory://test-server', $this->transport->getEndpoint());
    }

    public function testServerWithCustomContext(): void
    {
        $context = new TestContext(['debug' => true, 'app.name' => 'test-shell']);
        $server = new TestServer($context);
        $server->register(new EchoHandler());

        $transport = new InMemoryTransport($server);
        $shell = new Shell($transport, 'ctx> ');

        $transport->connect();
        $exitCode = $shell->executeCommand('echo contextual', $this->output);

        self::assertSame(0, $exitCode);

        // Verify context is accessible from server
        self::assertTrue($server->getContext()->has('debug'));
        self::assertSame('test-shell', $server->getContext()->get('app.name'));
    }

    public function testMultipleCommandsSequence(): void
    {
        $this->transport->connect();

        // Execute multiple commands
        $exitCode1 = $this->shell->executeCommand('echo first', $this->output);
        $output1 = $this->output->fetch();

        $exitCode2 = $this->shell->executeCommand('echo second', $this->output);
        $output2 = $this->output->fetch();

        $exitCode3 = $this->shell->executeCommand('fail oops', $this->output);
        $output3 = $this->output->fetch();

        self::assertSame(0, $exitCode1);
        self::assertStringContainsString('first', $output1);

        self::assertSame(0, $exitCode2);
        self::assertStringContainsString('second', $output2);

        self::assertSame(1, $exitCode3);
        self::assertStringContainsString('oops', $output3);
    }

    public function testReconnection(): void
    {
        $this->transport->connect();
        self::assertTrue($this->transport->isConnected());

        $this->transport->disconnect();
        self::assertFalse($this->transport->isConnected());

        $this->transport->connect();
        self::assertTrue($this->transport->isConnected());

        // Should work after reconnection
        $exitCode = $this->shell->executeCommand('echo reconnected', $this->output);
        self::assertSame(0, $exitCode);
    }
}
