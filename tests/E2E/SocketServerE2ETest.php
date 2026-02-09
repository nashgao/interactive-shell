<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\E2E;

use NashGao\InteractiveShell\Server\Handler\CommandRegistry;
use NashGao\InteractiveShell\Server\SocketServer;
use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\DelayHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\EchoHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\ErrorHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestContext;
use NashGao\InteractiveShell\Transport\UnixSocketTransport;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * End-to-end tests using real Unix sockets with Swoole.
 *
 * These tests verify the complete pipeline works with actual socket
 * communication, including serialization, network transport, and
 * server-side command handling.
 *
 * Requirements:
 * - Swoole PHP extension
 * - Write permissions to system temp directory
 *
 * Note: E2E tests with real Swoole servers require careful coroutine handling.
 * For most testing scenarios, prefer integration tests with InMemoryTransport.
 */
#[RequiresPhpExtension('swoole')]
final class SocketServerE2ETest extends TestCase
{
    private string $socketPath;

    protected function setUp(): void
    {
        $this->socketPath = sys_get_temp_dir() . '/interactive-shell-test-' . uniqid() . '.sock';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->socketPath)) {
            @unlink($this->socketPath);
        }
    }

    public function testTransportInfoWithoutServer(): void
    {
        $transport = new UnixSocketTransport($this->socketPath, timeout: 5.0);

        $info = $transport->getInfo();

        self::assertSame('unix_socket', $info['type']);
        self::assertSame($this->socketPath, $info['path']);
        self::assertFalse($info['connected']);
    }

    public function testTransportEndpoint(): void
    {
        $transport = new UnixSocketTransport($this->socketPath);

        self::assertSame("unix://{$this->socketPath}", $transport->getEndpoint());
    }

    public function testConnectionToNonexistentSocketFails(): void
    {
        $transport = new UnixSocketTransport('/nonexistent/path/socket.sock', timeout: 1.0);

        $this->expectException(\RuntimeException::class);
        $transport->connect();
    }

    public function testTransportSupportsStreaming(): void
    {
        $transport = new UnixSocketTransport($this->socketPath);

        self::assertTrue($transport->supportsStreaming());
    }

    public function testSocketServerCreation(): void
    {
        $registry = new CommandRegistry();
        $registry->register(new EchoHandler());

        $server = new SocketServer(
            $this->socketPath,
            $registry,
            new TestContext()
        );

        self::assertFalse($server->isRunning());
        self::assertSame($this->socketPath, $server->getEndpoint());
    }

    public function testCommandRegistryWithTestHandlers(): void
    {
        $registry = new CommandRegistry();
        $registry->register(new EchoHandler());
        $registry->register(new ErrorHandler());
        $registry->register(new DelayHandler());

        self::assertTrue($registry->has('echo'));
        self::assertTrue($registry->has('fail'));
        self::assertTrue($registry->has('delay'));
        self::assertFalse($registry->has('unknown'));
    }

    /**
     * Full E2E test with real socket server.
     *
     * This test runs the complete server-client communication using
     * Swoole coroutines. The server runs in a background coroutine
     * while the client executes commands.
     *
     * Note: This test may be flaky due to timing issues with coroutines.
     * For reliable testing, prefer integration tests with InMemoryTransport.
     */
    public function testFullRoundtripWithSwooleServer(): void
    {
        $exception = null;
        $testPassed = false;

        // Run entire test in Swoole coroutine context
        \Swoole\Coroutine\run(function () use (&$exception, &$testPassed): void {
            try {
                // Setup server
                $registry = new CommandRegistry();
                $registry->register(new EchoHandler());
                $registry->register(new ErrorHandler());

                $server = new SocketServer(
                    $this->socketPath,
                    $registry,
                    new TestContext(['test' => true])
                );

                // Start server in background coroutine
                $serverCoro = \Swoole\Coroutine::create(function () use ($server): void {
                    $server->start();
                });

                // Wait for server to be ready
                \Swoole\Coroutine::sleep(0.1);

                // Create client
                $transport = new UnixSocketTransport($this->socketPath, timeout: 5.0);
                $transport->connect();

                // Welcome message is now consumed by connect() — buffer is clean

                // Execute echo command via direct transport
                $parsed = new \NashGao\InteractiveShell\Parser\ParsedCommand(
                    'echo',
                    ['hello', 'from', 'e2e'],
                    [],
                    'echo hello from e2e',
                    false
                );

                $result = $transport->send($parsed);

                self::assertTrue($result->success, "Expected success but got error: " . ($result->error ?? 'none'));
                self::assertSame('hello from e2e', $result->data);

                // Execute fail command
                $failParsed = new \NashGao\InteractiveShell\Parser\ParsedCommand(
                    'fail',
                    ['E2E', 'test', 'error'],
                    [],
                    'fail E2E test error',
                    false
                );

                $failResult = $transport->send($failParsed);

                self::assertFalse($failResult->success);
                self::assertSame('E2E test error', $failResult->error);

                // Cleanup
                $transport->disconnect();
                $server->stop();

                $testPassed = true;
            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        self::assertTrue($testPassed, 'E2E test did not complete');
    }

    /**
     * Test multiple commands on same connection.
     */
    public function testMultipleCommandsOnConnection(): void
    {
        $exception = null;
        $results = [];

        \Swoole\Coroutine\run(function () use (&$exception, &$results): void {
            try {
                $registry = new CommandRegistry();
                $registry->register(new EchoHandler());

                $server = new SocketServer(
                    $this->socketPath,
                    $registry,
                    new TestContext()
                );

                \Swoole\Coroutine::create(function () use ($server): void {
                    $server->start();
                });

                \Swoole\Coroutine::sleep(0.1);

                $transport = new UnixSocketTransport($this->socketPath, timeout: 5.0);
                $transport->connect();

                // Welcome message is now consumed by connect() — buffer is clean

                // Multiple commands
                for ($i = 1; $i <= 3; ++$i) {
                    $parsed = new \NashGao\InteractiveShell\Parser\ParsedCommand(
                        'echo',
                        ["message-{$i}"],
                        [],
                        "echo message-{$i}",
                        false
                    );

                    $result = $transport->send($parsed);
                    $results[] = $result->data;
                }

                $transport->disconnect();
                $server->stop();
            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        self::assertSame(['message-1', 'message-2', 'message-3'], $results);
    }
}
