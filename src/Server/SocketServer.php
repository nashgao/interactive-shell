<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Parser\ShellParser;
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;
use Swoole\Coroutine;
use Swoole\Coroutine\Server as SwooleServer;
use Swoole\Coroutine\Server\Connection;

/**
 * Swoole coroutine-based Unix socket server for shell connections.
 *
 * This server uses Swoole's coroutine server to handle multiple shell
 * clients concurrently. Each client connection runs in its own coroutine.
 */
final class SocketServer implements ServerInterface
{
    private ?SwooleServer $server = null;
    private bool $running = false;
    private ShellParser $parser;

    /**
     * @param string $socketPath Path to Unix socket file
     * @param CommandRegistry $registry Command handler registry
     * @param ContextInterface $context Framework context
     * @param int $socketPermissions Unix permissions for socket file (default: 0660)
     */
    public function __construct(
        private readonly string $socketPath,
        private readonly CommandRegistry $registry,
        private readonly ContextInterface $context,
        private readonly int $socketPermissions = 0660
    ) {
        $this->parser = new ShellParser();
    }

    public function start(): void
    {
        if ($this->running) {
            return;
        }

        $this->cleanupSocket();
        $this->createServer();
        $this->running = true;

        $this->server?->start();
    }

    public function stop(): void
    {
        if (!$this->running) {
            return;
        }

        $this->running = false;
        $this->server?->shutdown();
        $this->cleanupSocket();
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getEndpoint(): string
    {
        return $this->socketPath;
    }

    private function createServer(): void
    {
        $this->server = new SwooleServer('unix:' . $this->socketPath);

        $this->server->handle(function (Connection $conn): void {
            $this->handleConnection($conn);
        });

        // Set socket permissions after server starts listening
        Coroutine::create(function (): void {
            // Small delay to ensure socket file exists
            Coroutine::sleep(0.01);
            if (file_exists($this->socketPath)) {
                chmod($this->socketPath, $this->socketPermissions);
            }
        });
    }

    private function handleConnection(Connection $conn): void
    {
        try {
            $this->sendWelcome($conn);

            while ($this->running) {
                $data = $conn->recv(timeout: 30.0);

                if ($data === '') {
                    break; // Client genuinely disconnected
                }

                if ($data === false) {
                    continue; // Timeout — keep waiting, re-check $this->running
                }

                $response = $this->processRequest($data);
                $this->sendResponse($conn, $response);
            }
        } catch (\Throwable $e) {
            $this->sendResponse($conn, CommandResult::failure(
                'Server error: ' . $e->getMessage()
            ));
        } finally {
            $conn->close();
        }
    }

    private function sendWelcome(Connection $conn): void
    {
        $welcome = [
            'type' => 'welcome',
            'message' => 'Connected to interactive shell server',
            'version' => '1.0.0',
            'commands' => $this->registry->getCommandList(),
        ];

        $this->sendJson($conn, $welcome);
    }

    private function processRequest(string $data): CommandResult
    {
        $data = trim($data);

        // Try JSON protocol first
        $decoded = json_decode($data, true);
        if (is_array($decoded) && ($decoded['type'] ?? '') === 'ping') {
            return CommandResult::success(['pong' => true, 'time' => date('c')]);
        }
        if (is_array($decoded) && isset($decoded['command'])) {
            return $this->processJsonRequest($decoded);
        }

        // Fall back to raw command parsing
        return $this->processRawCommand($data);
    }

    /**
     * @param array{command: string, arguments?: array<mixed>, options?: array<string, mixed>} $request
     */
    private function processJsonRequest(array $request): CommandResult
    {
        // Ensure arguments are properly indexed strings
        $arguments = [];
        foreach (($request['arguments'] ?? []) as $key => $value) {
            $arguments[] = is_scalar($value) ? (string) $value : '';
        }

        $command = new ParsedCommand(
            command: $request['command'],
            arguments: $arguments,
            options: $request['options'] ?? [],
            raw: json_encode($request) ?: '',
            hasVerticalTerminator: false
        );

        return $this->registry->execute($command, $this->context);
    }

    private function processRawCommand(string $raw): CommandResult
    {
        if ($raw === '') {
            return CommandResult::success(null);
        }

        try {
            $command = $this->parser->parse($raw);
            return $this->registry->execute($command, $this->context);
        } catch (\Throwable $e) {
            return CommandResult::failure('Parse error: ' . $e->getMessage());
        }
    }

    private function sendResponse(Connection $conn, CommandResult $result): void
    {
        $this->sendJson($conn, [
            'type' => 'response',
            'success' => $result->success,
            'data' => $result->data,
            'error' => $result->error,
            'message' => $result->message,
            'metadata' => $result->metadata,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendJson(Connection $conn, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode(['error' => 'JSON encoding failed']);
        }

        $conn->send($json . "\n");
    }

    private function cleanupSocket(): void
    {
        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }
    }
}
