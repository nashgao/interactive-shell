<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\E2E\Hyperf;

use Hyperf\Contract\ConfigInterface;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\Hyperf\ShellProcess;
use NashGao\InteractiveShell\Transport\UnixSocketTransport;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Hyperf E2E Specification Tests.
 *
 * These tests verify the complete Hyperf integration from the CONSUMER'S perspective:
 * - A consumer boots Hyperf with interactive-shell configured
 * - The ShellProcess starts and listens on a socket
 * - Clients can connect and execute commands
 *
 * SPECIFICATION-FIRST: These tests define what a Hyperf consumer expects,
 * not what the implementation currently does.
 */
#[RequiresPhpExtension('swoole')]
final class HyperfServerE2ETest extends TestCase
{
    private ?HyperfTestApplication $app = null;
    private string $socketPath = '';

    protected function setUp(): void
    {
        if (!interface_exists(ConfigInterface::class)) {
            $this->markTestSkipped('Hyperf/Contract not installed');
        }

        $this->socketPath = sys_get_temp_dir() . '/hyperf-shell-e2e-' . uniqid() . '.sock';

        $this->app = new HyperfTestApplication([
            'interactive_shell.enabled' => true,
            'interactive_shell.socket_path' => $this->socketPath,
            'app_name' => 'e2e-test-app',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->socketPath !== '' && file_exists($this->socketPath)) {
            @unlink($this->socketPath);
        }
    }

    /**
     * SPECIFICATION: When a consumer boots Hyperf with interactive-shell,
     * the shell server starts and accepts client connections.
     */
    public function testConsumerCanConnectToHyperfShellServer(): void
    {
        $exception = null;
        $connectionSuccessful = false;

        \Swoole\Coroutine\run(function () use (&$exception, &$connectionSuccessful): void {
            try {
                // Given: A Hyperf application with shell config
                $process = new ShellProcess(
                    $this->app->getContainer(),
                    $this->app->getConfig()
                );

                // When: The process starts (simulating Hyperf boot)
                \Swoole\Coroutine::create(function () use ($process): void {
                    $process->handle();
                });

                // Allow server to start
                \Swoole\Coroutine::sleep(0.1);

                // Then: A client can connect
                $transport = new UnixSocketTransport($this->socketPath, 5.0);
                $transport->connect();

                $connectionSuccessful = $transport->isConnected();

                // Cleanup
                $transport->disconnect();
                $process->stop();

            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        self::assertTrue($connectionSuccessful, 'Consumer should be able to connect to Hyperf shell server');
    }

    /**
     * SPECIFICATION: A consumer can execute commands through the Hyperf shell
     * and receive proper responses.
     */
    public function testConsumerCanExecuteCommandsViaHyperfShell(): void
    {
        $exception = null;
        $pingResult = null;

        \Swoole\Coroutine\run(function () use (&$exception, &$pingResult): void {
            try {
                // Given: A running Hyperf shell server
                $process = new ShellProcess(
                    $this->app->getContainer(),
                    $this->app->getConfig()
                );

                \Swoole\Coroutine::create(function () use ($process): void {
                    $process->handle();
                });

                \Swoole\Coroutine::sleep(0.1);

                // When: Consumer connects and executes the built-in ping command
                $transport = new UnixSocketTransport($this->socketPath, 5.0);
                $transport->connect();

                // Consume welcome message
                $transport->receive(1.0);

                // Execute ping command
                $parsed = new ParsedCommand('ping', [], [], 'ping', false);
                $result = $transport->send($parsed);
                $pingResult = $result;

                $transport->disconnect();
                $process->stop();

            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        // Then: Consumer receives a successful response
        self::assertNotNull($pingResult, 'Should receive a result from ping command');
        self::assertTrue($pingResult->success, 'Ping command should succeed');
        self::assertSame('pong', $pingResult->data, 'Ping should return "pong"');
    }

    /**
     * SPECIFICATION: The built-in config command returns Hyperf configuration
     * accessible to shell consumers.
     */
    public function testBuiltInConfigCommandReturnsHyperfConfig(): void
    {
        $exception = null;
        $configResult = null;

        \Swoole\Coroutine\run(function () use (&$exception, &$configResult): void {
            try {
                $process = new ShellProcess(
                    $this->app->getContainer(),
                    $this->app->getConfig()
                );

                \Swoole\Coroutine::create(function () use ($process): void {
                    $process->handle();
                });

                \Swoole\Coroutine::sleep(0.1);

                $transport = new UnixSocketTransport($this->socketPath, 5.0);
                $transport->connect();
                $transport->receive(1.0);

                // When: Consumer requests config
                $parsed = new ParsedCommand('config', [], [], 'config', false);
                $configResult = $transport->send($parsed);

                $transport->disconnect();
                $process->stop();

            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        // Then: Consumer receives config data
        self::assertNotNull($configResult, 'Should receive config result');
        self::assertTrue($configResult->success, 'Config command should succeed');
        self::assertIsArray($configResult->data, 'Config data should be an array');
    }

    /**
     * SPECIFICATION: The command list is available to shell consumers.
     */
    public function testBuiltInCommandListReturnsAvailableCommands(): void
    {
        $exception = null;
        $commandsResult = null;

        \Swoole\Coroutine\run(function () use (&$exception, &$commandsResult): void {
            try {
                $process = new ShellProcess(
                    $this->app->getContainer(),
                    $this->app->getConfig()
                );

                \Swoole\Coroutine::create(function () use ($process): void {
                    $process->handle();
                });

                \Swoole\Coroutine::sleep(0.1);

                $transport = new UnixSocketTransport($this->socketPath, 5.0);
                $transport->connect();
                $transport->receive(1.0);

                // When: Consumer requests command list
                $parsed = new ParsedCommand('commands', [], [], 'commands', false);
                $commandsResult = $transport->send($parsed);

                $transport->disconnect();
                $process->stop();

            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        // Then: Consumer receives list of available commands
        self::assertNotNull($commandsResult, 'Should receive commands result');
        self::assertTrue($commandsResult->success, 'Commands listing should succeed');
        self::assertIsArray($commandsResult->data, 'Commands data should be an array');

        // Built-in commands should be present
        $commands = array_column($commandsResult->data, 'name');
        self::assertContains('ping', $commands, 'ping should be in command list');
        self::assertContains('config', $commands, 'config should be in command list');
        self::assertContains('help', $commands, 'help should be in command list');
    }

    /**
     * SPECIFICATION: Help command provides usage information for commands.
     */
    public function testBuiltInHelpCommandProvidesUsageInfo(): void
    {
        $exception = null;
        $helpResult = null;

        \Swoole\Coroutine\run(function () use (&$exception, &$helpResult): void {
            try {
                $process = new ShellProcess(
                    $this->app->getContainer(),
                    $this->app->getConfig()
                );

                \Swoole\Coroutine::create(function () use ($process): void {
                    $process->handle();
                });

                \Swoole\Coroutine::sleep(0.1);

                $transport = new UnixSocketTransport($this->socketPath, 5.0);
                $transport->connect();
                $transport->receive(1.0);

                // When: Consumer requests help for a command
                $parsed = new ParsedCommand('help', ['ping'], [], 'help ping', false);
                $helpResult = $transport->send($parsed);

                $transport->disconnect();
                $process->stop();

            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        // Then: Consumer receives help information
        self::assertNotNull($helpResult, 'Should receive help result');
        self::assertTrue($helpResult->success, 'Help command should succeed');
    }

    /**
     * SPECIFICATION: Multiple clients can connect and execute commands independently.
     */
    public function testMultipleClientsCanConnectSimultaneously(): void
    {
        $exception = null;
        $client1Result = null;
        $client2Result = null;

        \Swoole\Coroutine\run(function () use (&$exception, &$client1Result, &$client2Result): void {
            try {
                $process = new ShellProcess(
                    $this->app->getContainer(),
                    $this->app->getConfig()
                );

                \Swoole\Coroutine::create(function () use ($process): void {
                    $process->handle();
                });

                \Swoole\Coroutine::sleep(0.1);

                // When: Two clients connect and execute commands
                $transport1 = new UnixSocketTransport($this->socketPath, 5.0);
                $transport2 = new UnixSocketTransport($this->socketPath, 5.0);

                $transport1->connect();
                $transport2->connect();

                $transport1->receive(1.0);
                $transport2->receive(1.0);

                $parsed = new ParsedCommand('ping', [], [], 'ping', false);

                $client1Result = $transport1->send($parsed);
                $client2Result = $transport2->send($parsed);

                $transport1->disconnect();
                $transport2->disconnect();
                $process->stop();

            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception !== null) {
            throw $exception;
        }

        // Then: Both clients receive successful responses
        self::assertTrue($client1Result->success, 'Client 1 should succeed');
        self::assertTrue($client2Result->success, 'Client 2 should succeed');
        self::assertSame('pong', $client1Result->data);
        self::assertSame('pong', $client2Result->data);
    }
}
