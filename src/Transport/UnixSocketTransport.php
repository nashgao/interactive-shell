<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Transport;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Parser\ParsedCommand;

/**
 * Unix Socket transport for bidirectional streaming communication.
 *
 * Supports both request/response and streaming modes for real-time
 * message delivery (e.g., MQTT debug shell).
 */
final class UnixSocketTransport implements StreamingTransportInterface
{
    // POSIX socket error codes (platform-specific)
    private const EAGAIN = 11;           // Linux: Resource temporarily unavailable
    private const EWOULDBLOCK = 35;      // BSD/macOS: Operation would block
    private const ECONNRESET = 104;      // Linux: Connection reset by peer
    private const ECONNRESET_BSD = 54;   // BSD/macOS: Connection reset by peer
    private const EPIPE = 32;            // Broken pipe
    private const ENOTCONN = 107;        // Linux: Transport endpoint not connected
    private const ENOTCONN_BSD = 57;     // BSD/macOS: Transport endpoint not connected

    private ?\Socket $socket = null;
    private bool $connected = false;
    private bool $streaming = false;
    /** @var callable(Message): void|null */
    private $messageCallback = null;

    public function __construct(
        private readonly string $socketPath,
        private readonly float $timeout = 0.0,
    ) {}

    public function connect(): void
    {
        if ($this->socket !== null) {
            return;
        }

        $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($socket === false) {
            throw new \RuntimeException('Failed to create socket: ' . socket_strerror(socket_last_error()));
        }

        $result = @socket_connect($socket, $this->socketPath);
        if ($result === false) {
            $error = socket_strerror(socket_last_error($socket));
            socket_close($socket);
            throw new \RuntimeException("Failed to connect to {$this->socketPath}: {$error}");
        }

        // Set receive timeout only if specified (0 = no timeout)
        if ($this->timeout > 0) {
            $timeoutSec = (int) $this->timeout;
            $timeoutUsec = (int) (($this->timeout - $timeoutSec) * 1000000);
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, [
                'sec' => $timeoutSec,
                'usec' => $timeoutUsec,
            ]);
        }

        $this->socket = $socket;
        $this->connected = true;
    }

    public function disconnect(): void
    {
        if ($this->socket !== null) {
            $this->streaming = false;
            $this->connected = false;
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null && $this->connected;
    }

    public function send(ParsedCommand $command): CommandResult
    {
        if ($this->socket === null) {
            return CommandResult::failure('Not connected');
        }

        $request = [
            'type' => 'command',
            'command' => $command->command,
            'arguments' => $command->arguments,
            'options' => $command->options,
        ];

        $json = json_encode($request, JSON_THROW_ON_ERROR) . "\n";
        $written = @socket_write($this->socket, $json);

        if ($written === false) {
            $error = socket_last_error($this->socket);
            if ($this->isDisconnectionError($error)) {
                $this->connected = false;
            }
            socket_clear_error($this->socket);
            return CommandResult::failure('Failed to send command: ' . socket_strerror($error));
        }

        // Read response
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
        if ($this->socket === null) {
            return false;
        }

        $ping = json_encode(['type' => 'ping']) . "\n";
        $written = @socket_write($this->socket, $ping);

        if ($written === false) {
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
            'type' => 'unix_socket',
            'path' => $this->socketPath,
            'connected' => $this->isConnected(),
            'streaming' => $this->streaming,
        ];
    }

    public function getEndpoint(): string
    {
        return "unix://{$this->socketPath}";
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function sendAsync(ParsedCommand $command): void
    {
        if ($this->socket === null) {
            throw new \RuntimeException('Not connected');
        }

        $request = [
            'type' => 'command',
            'command' => $command->command,
            'arguments' => $command->arguments,
            'options' => $command->options,
            'async' => true,
        ];

        $json = json_encode($request, JSON_THROW_ON_ERROR) . "\n";
        @socket_write($this->socket, $json);
    }

    public function receive(float $timeout = -1): ?Message
    {
        if ($this->socket === null) {
            return null;
        }

        // Set temporary timeout if specified
        if ($timeout >= 0) {
            $timeoutSec = (int) $timeout;
            $timeoutUsec = (int) (($timeout - $timeoutSec) * 1000000);
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
                'sec' => $timeoutSec,
                'usec' => $timeoutUsec,
            ]);
        }

        $line = $this->readLine($timeout >= 0 ? $timeout : null);

        // Restore original timeout
        if ($timeout >= 0) {
            $timeoutSec = (int) $this->timeout;
            $timeoutUsec = (int) (($this->timeout - $timeoutSec) * 1000000);
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
                'sec' => $timeoutSec,
                'usec' => $timeoutUsec,
            ]);
        }

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

    /**
     * Invoke the message callback if set.
     */
    public function dispatchMessage(Message $message): void
    {
        if ($this->messageCallback !== null) {
            ($this->messageCallback)($message);
        }
    }

    public function startStreaming(): void
    {
        if ($this->socket === null) {
            throw new \RuntimeException('Not connected');
        }

        // Send subscribe command
        $subscribe = json_encode(['type' => 'subscribe']) . "\n";
        @socket_write($this->socket, $subscribe);

        $this->streaming = true;
    }

    public function stopStreaming(): void
    {
        if ($this->socket === null) {
            return;
        }

        // Send unsubscribe command
        $unsubscribe = json_encode(['type' => 'unsubscribe']) . "\n";
        @socket_write($this->socket, $unsubscribe);

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
        if ($this->socket === null) {
            return null;
        }

        $buffer = '';
        $startTime = microtime(true);
        $maxTime = $timeout !== null ? $startTime + $timeout : null;

        while (true) {
            if ($maxTime !== null && microtime(true) > $maxTime) {
                return null; // Timeout
            }

            $char = @socket_read($this->socket, 1);

            if ($char === false || $char === '') {
                $error = socket_last_error($this->socket);

                // Timeout errors - return null gracefully
                if ($this->isTimeoutError($error)) {
                    socket_clear_error($this->socket);
                    return null;
                }

                // Connection errors - mark disconnected
                if ($this->isDisconnectionError($error)) {
                    $this->connected = false;
                    socket_clear_error($this->socket);
                    return null;
                }

                // Unknown error - also treat as potential disconnection
                socket_clear_error($this->socket);
                return null;
            }

            if ($char === "\n") {
                return $buffer;
            }

            $buffer .= $char;
        }
    }

    /**
     * Check if error code indicates a timeout (non-fatal).
     */
    private function isTimeoutError(int $error): bool
    {
        return $error === self::EAGAIN || $error === self::EWOULDBLOCK;
    }

    /**
     * Check if error code indicates a disconnection (connection lost).
     */
    private function isDisconnectionError(int $error): bool
    {
        return in_array($error, [
            self::ECONNRESET,
            self::ECONNRESET_BSD,
            self::EPIPE,
            self::ENOTCONN,
            self::ENOTCONN_BSD,
        ], true);
    }
}
