<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Transport;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use RuntimeException;
use Swoole\Coroutine\Client;

/**
 * Swoole coroutine-based Unix Socket transport for high-throughput scenarios.
 *
 * Uses Swoole\Coroutine\Client for non-blocking I/O within coroutine context.
 * Supports both request/response and streaming modes.
 */
final class SwooleSocketTransport implements StreamingTransportInterface
{
    private ?Client $client = null;
    private bool $connected = false;
    private bool $streaming = false;
    private string $readBuffer = '';
    /** @var callable(Message): void|null */
    private $messageCallback = null;

    public function __construct(
        private readonly string $socketPath,
        private readonly float $timeout = 0.0,
    ) {}

    public function connect(): void
    {
        if ($this->client !== null && $this->connected) {
            return;
        }

        $this->client = new Client(SWOOLE_SOCK_UNIX_STREAM);

        if ($this->timeout > 0) {
            $this->client->set([
                'timeout' => $this->timeout,
                'connect_timeout' => $this->timeout,
                'read_timeout' => $this->timeout,
                'write_timeout' => $this->timeout,
            ]);
        }

        if (! $this->client->connect($this->socketPath)) {
            $errCode = $this->client->errCode;
            $errMsg = $this->client->errMsg;
            $this->client = null;
            throw new RuntimeException("Failed to connect to {$this->socketPath}: [{$errCode}] {$errMsg}");
        }

        $this->connected = true;

        // Server sends an unsolicited welcome message on connect — drain it
        $this->readLine(2.0);
    }

    public function disconnect(): void
    {
        if ($this->client !== null) {
            $this->streaming = false;
            $this->connected = false;
            $this->client->close();
            $this->client = null;
            $this->readBuffer = '';
        }
    }

    public function isConnected(): bool
    {
        return $this->client !== null && $this->connected && $this->client->isConnected();
    }

    public function send(ParsedCommand $command): CommandResult
    {
        if (! $this->isConnected()) {
            return CommandResult::failure('Not connected');
        }

        $request = [
            'type' => 'command',
            'command' => $command->command,
            'arguments' => $command->arguments,
            'options' => $command->options,
        ];

        $json = json_encode($request, JSON_THROW_ON_ERROR) . "\n";

        if ($this->client === null || ! $this->client->send($json)) {
            $this->handleConnectionError();
            return CommandResult::failure('Failed to send command: ' . ($this->client?->errMsg ?? 'Connection lost'));
        }

        $response = $this->readLine();
        if ($response === null) {
            return CommandResult::failure('No response from server');
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            return CommandResult::fromResponse($data);
        } catch (\JsonException $e) {
            return CommandResult::failure("Invalid response: {$e->getMessage()}");
        }
    }

    public function ping(): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        $ping = json_encode(['type' => 'ping']) . "\n";

        if ($this->client === null || ! $this->client->send($ping)) {
            return false;
        }

        $response = $this->readLine(1.0);
        return $response !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return [
            'type' => 'swoole_socket',
            'path' => $this->socketPath,
            'connected' => $this->isConnected(),
            'streaming' => $this->streaming,
            'timeout' => $this->timeout,
        ];
    }

    public function getEndpoint(): string
    {
        return "swoole+unix://{$this->socketPath}";
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function sendAsync(ParsedCommand $command): void
    {
        if (! $this->isConnected()) {
            throw new RuntimeException('Not connected');
        }

        $request = [
            'type' => 'command',
            'command' => $command->command,
            'arguments' => $command->arguments,
            'options' => $command->options,
            'async' => true,
        ];

        $json = json_encode($request, JSON_THROW_ON_ERROR) . "\n";

        if ($this->client === null || ! $this->client->send($json)) {
            $this->handleConnectionError();
            throw new RuntimeException('Failed to send async command');
        }
    }

    public function receive(float $timeout = -1): ?Message
    {
        if (! $this->isConnected()) {
            return null;
        }

        $effectiveTimeout = $timeout >= 0 ? $timeout : ($this->timeout > 0 ? $this->timeout : -1);
        $line = $this->readLine($effectiveTimeout);

        if ($line === null) {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            return Message::fromArray($data);
        } catch (\JsonException) {
            return Message::error("Invalid message format: {$line}");
        }
    }

    public function onMessage(callable $callback): void
    {
        $this->messageCallback = $callback;
    }

    public function startStreaming(): void
    {
        if (! $this->isConnected()) {
            throw new RuntimeException('Not connected');
        }

        $subscribe = json_encode(['type' => 'subscribe']) . "\n";

        if ($this->client === null || ! $this->client->send($subscribe)) {
            throw new RuntimeException('Failed to start streaming');
        }

        $this->streaming = true;
    }

    public function stopStreaming(): void
    {
        if (! $this->isConnected()) {
            return;
        }

        $unsubscribe = json_encode(['type' => 'unsubscribe']) . "\n";
        $this->client?->send($unsubscribe);
        $this->streaming = false;
    }

    public function isStreaming(): bool
    {
        return $this->streaming;
    }

    /**
     * Read a line from the socket.
     */
    private function readLine(?float $timeout = null): ?string
    {
        if ($this->client === null) {
            return null;
        }

        // Check buffer for existing complete line
        $newlinePos = strpos($this->readBuffer, "\n");
        if ($newlinePos !== false) {
            $line = substr($this->readBuffer, 0, $newlinePos);
            $this->readBuffer = substr($this->readBuffer, $newlinePos + 1);
            return $line;
        }

        // Read more data from socket
        $effectiveTimeout = $timeout ?? ($this->timeout > 0 ? $this->timeout : -1);

        while (true) {
            $data = $this->client->recv($effectiveTimeout);

            if ($data === false || $data === '') {
                if ($this->client->errCode !== 0) {
                    $this->handleConnectionError();
                }
                return null;
            }

            $this->readBuffer .= $data;

            $newlinePos = strpos($this->readBuffer, "\n");
            if ($newlinePos !== false) {
                $line = substr($this->readBuffer, 0, $newlinePos);
                $this->readBuffer = substr($this->readBuffer, $newlinePos + 1);
                return $line;
            }
        }
    }

    /**
     * Handle connection errors by marking as disconnected.
     */
    private function handleConnectionError(): void
    {
        $this->connected = false;
    }

    /**
     * Invoke the message callback if set.
     */
    public function dispatchMessage(Message $message): void
    {
        if ($this->messageCallback !== null) {
            ($this->messageCallback)($message);
        }
    }
}
