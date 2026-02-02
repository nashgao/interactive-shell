#!/usr/bin/env php
<?php

/**
 * Unix Socket Server Example
 *
 * A pure PHP Unix socket server that handles interactive shell commands.
 * This demonstrates real inter-process communication without requiring Swoole.
 *
 * Run with: php examples/server.php
 *
 * Then in another terminal: php examples/client.php
 *
 * Protocol:
 *   Request:  {"type":"command","command":"...","arguments":[...],"options":{...}}\n
 *   Response: {"success":true,"data":...,"error":null,"message":null}\n
 */

declare(strict_types=1);

const SOCKET_PATH = '/tmp/interactive-shell.sock';

/** @var array<int, array{id: int, name: string, email: string, role: string}> */
$users = [
    ['id' => 1, 'name' => 'Alice Johnson', 'email' => 'alice@example.com', 'role' => 'admin'],
    ['id' => 2, 'name' => 'Bob Smith', 'email' => 'bob@example.com', 'role' => 'user'],
    ['id' => 3, 'name' => 'Carol White', 'email' => 'carol@example.com', 'role' => 'user'],
    ['id' => 4, 'name' => 'David Brown', 'email' => 'david@example.com', 'role' => 'moderator'],
];

$startTime = time();

/**
 * Handle incoming commands.
 *
 * @param array<string, mixed> $request
 * @return array<string, mixed>
 */
function handleRequest(array $request): array
{
    global $users, $startTime;

    $rawType = $request['type'] ?? '';
    /** @var string $type */
    $type = is_string($rawType) ? $rawType : '';

    // Handle ping
    if ($type === 'ping') {
        return [
            'success' => true,
            'data' => ['pong' => true, 'time' => date('c')],
        ];
    }

    // Handle subscribe/unsubscribe (for streaming)
    if ($type === 'subscribe' || $type === 'unsubscribe') {
        return [
            'success' => true,
            'data' => ['subscribed' => $type === 'subscribe'],
        ];
    }

    // Handle commands
    if ($type !== 'command') {
        return [
            'success' => false,
            'error' => "Unknown request type: {$type}",
        ];
    }

    $rawCommand = $request['command'] ?? '';
    /** @var string $command */
    $command = is_string($rawCommand) ? $rawCommand : '';
    /** @var array<int, mixed> $arguments */
    $arguments = is_array($request['arguments'] ?? []) ? $request['arguments'] : [];
    /** @var array<string, mixed> $options */
    $options = is_array($request['options'] ?? []) ? $request['options'] : [];

    return match ($command) {
        'users' => handleUsers($arguments, $options, $users),
        'ping' => handlePing(),
        'echo' => handleEcho($arguments),
        'server' => handleServer($arguments, $startTime),
        default => [
            'success' => false,
            'error' => "Unknown command: {$command}. Available: users, ping, echo, server",
        ],
    };
}

/**
 * Handle 'users' command.
 *
 * @param array<int, mixed> $arguments
 * @param array<string, mixed> $options
 * @param array<int, array{id: int, name: string, email: string, role: string}> $users
 * @return array<string, mixed>
 */
function handleUsers(array $arguments, array $options, array $users): array
{
    $arg0 = $arguments[0] ?? 'list';
    $subcommand = is_string($arg0) ? $arg0 : (is_scalar($arg0) ? (string) $arg0 : 'list');

    return match ($subcommand) {
        'list' => handleUsersList($options, $users),
        'get' => handleUsersGet($arguments, $users),
        'count' => [
            'success' => true,
            'data' => ['count' => count($users)],
            'message' => 'User count retrieved',
        ],
        default => [
            'success' => false,
            'error' => "Unknown users subcommand: {$subcommand}. Try: list, get, count",
        ],
    };
}

/**
 * Handle 'users list' command.
 *
 * @param array<string, mixed> $options
 * @param array<int, array{id: int, name: string, email: string, role: string}> $users
 * @return array<string, mixed>
 */
function handleUsersList(array $options, array $users): array
{
    $role = $options['role'] ?? null;

    if ($role !== null) {
        $users = array_values(array_filter($users, fn ($u) => $u['role'] === $role));
    }

    return [
        'success' => true,
        'data' => $users,
        'message' => sprintf('Found %d user(s)', count($users)),
    ];
}

/**
 * Handle 'users get' command.
 *
 * @param array<int, mixed> $arguments
 * @param array<int, array{id: int, name: string, email: string, role: string}> $users
 * @return array<string, mixed>
 */
function handleUsersGet(array $arguments, array $users): array
{
    if (!isset($arguments[1]) || !is_numeric($arguments[1])) {
        return [
            'success' => false,
            'error' => 'Usage: users get <id>',
        ];
    }

    $id = (int) $arguments[1];
    foreach ($users as $user) {
        if ($user['id'] === $id) {
            return [
                'success' => true,
                'data' => $user,
                'message' => "User {$id} found",
            ];
        }
    }

    return [
        'success' => false,
        'error' => "User with ID {$id} not found",
    ];
}

/**
 * Handle 'ping' command.
 *
 * @return array<string, mixed>
 */
function handlePing(): array
{
    return [
        'success' => true,
        'data' => [
            'message' => 'pong',
            'time' => date('Y-m-d H:i:s'),
            'server' => 'unix-socket-server',
        ],
        'message' => 'Server is responding',
    ];
}

/**
 * Handle 'echo' command.
 *
 * @param array<int, mixed> $arguments
 * @return array<string, mixed>
 */
function handleEcho(array $arguments): array
{
    $stringArgs = array_map(static fn (mixed $arg): string => is_scalar($arg) ? (string) $arg : '', $arguments);
    $message = implode(' ', $stringArgs);
    if ($message === '') {
        return [
            'success' => false,
            'error' => 'Usage: echo <message>',
        ];
    }

    return [
        'success' => true,
        'data' => ['echo' => $message],
    ];
}

/**
 * Handle 'server' command.
 *
 * @param array<int, mixed> $arguments
 * @return array<string, mixed>
 */
function handleServer(array $arguments, int $startTime): array
{
    $arg0 = $arguments[0] ?? 'info';
    $subcommand = is_string($arg0) ? $arg0 : (is_scalar($arg0) ? (string) $arg0 : 'info');

    $uptimeSeconds = time() - $startTime;
    $uptime = formatUptime($uptimeSeconds);

    return match ($subcommand) {
        'info' => [
            'success' => true,
            'data' => [
                'name' => 'Unix Socket Server',
                'version' => '1.0.0',
                'uptime' => $uptime,
                'memory' => formatBytes(memory_get_usage(true)),
                'pid' => getmypid(),
                'socket' => SOCKET_PATH,
            ],
        ],
        'time' => [
            'success' => true,
            'data' => ['server_time' => date('c')],
        ],
        default => [
            'success' => false,
            'error' => "Unknown server subcommand: {$subcommand}. Try: info, time",
        ],
    };
}

/**
 * Format uptime in human readable format.
 */
function formatUptime(int $seconds): string
{
    if ($seconds < 60) {
        return "{$seconds} seconds";
    }

    $minutes = (int) ($seconds / 60);
    $remainingSeconds = $seconds % 60;

    if ($minutes < 60) {
        return "{$minutes} min, {$remainingSeconds} sec";
    }

    $hours = (int) ($minutes / 60);
    $remainingMinutes = $minutes % 60;

    if ($hours < 24) {
        return "{$hours} hours, {$remainingMinutes} min";
    }

    $days = (int) ($hours / 24);
    $remainingHours = $hours % 24;

    return "{$days} days, {$remainingHours} hours";
}

/**
 * Format bytes in human readable format.
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    $value = (float) $bytes;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    return round($value, 1) . ' ' . $units[$unitIndex];
}

/**
 * Read a line from the socket.
 */
function socketReadLine(\Socket $client): ?string
{
    $buffer = '';
    while (true) {
        $char = @socket_read($client, 1);
        if ($char === false || $char === '') {
            return $buffer !== '' ? $buffer : null;
        }
        if ($char === "\n") {
            return $buffer;
        }
        $buffer .= $char;
    }
}

/**
 * Log a message with timestamp.
 */
function serverLog(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
}

// ============================================================================
// Main Server
// ============================================================================

// Cleanup stale socket
if (file_exists(SOCKET_PATH)) {
    unlink(SOCKET_PATH);
}

// Create Unix socket
$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
if ($socket === false) {
    serverLog('ERROR: Failed to create socket: ' . socket_strerror(socket_last_error()));
    exit(1);
}

// Bind to socket path
if (!socket_bind($socket, SOCKET_PATH)) {
    serverLog('ERROR: Failed to bind socket: ' . socket_strerror(socket_last_error($socket)));
    socket_close($socket);
    exit(1);
}

// Start listening
if (!socket_listen($socket, 5)) {
    serverLog('ERROR: Failed to listen on socket: ' . socket_strerror(socket_last_error($socket)));
    socket_close($socket);
    exit(1);
}

// Set socket permissions
chmod(SOCKET_PATH, 0777);

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║              Unix Socket Server Started                      ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  Socket: " . str_pad(SOCKET_PATH, 51) . " ║\n";
echo "║  PID:    " . str_pad((string) getmypid(), 51) . " ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  Commands: users, ping, echo, server                         ║\n";
echo "║  Protocol: JSON with newline framing                         ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  Connect with: php examples/client.php                       ║\n";
echo "║  Press Ctrl+C to stop the server                             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

serverLog('Server listening...');

// Handle shutdown gracefully
$shutdown = false;
pcntl_signal(SIGINT, function () use (&$shutdown) {
    $shutdown = true;
});
pcntl_signal(SIGTERM, function () use (&$shutdown) {
    $shutdown = true;
});

// Main server loop
while (!$shutdown) {
    pcntl_signal_dispatch();

    // Non-blocking accept with timeout
    socket_set_nonblock($socket);
    $client = @socket_accept($socket);

    if ($client === false) {
        usleep(100000); // 100ms
        continue;
    }

    // Set client to blocking mode for simpler I/O
    socket_set_block($client);

    serverLog('Client connected');

    // Handle multiple commands from this client (persistent connection)
    while (true) {
        // Read request
        $request = socketReadLine($client);
        if ($request === null) {
            serverLog('Client disconnected');
            break;
        }

        serverLog("Received: {$request}");

        // Parse JSON
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($request, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $response = [
                'success' => false,
                'error' => "Invalid JSON: {$e->getMessage()}",
            ];
            $responseJson = json_encode($response) . "\n";
            socket_write($client, $responseJson);
            continue; // Don't close, wait for next command
        }

        // Handle request
        $response = handleRequest($data);
        $responseJson = json_encode($response, JSON_THROW_ON_ERROR) . "\n";

        serverLog("Sent: " . trim($responseJson));

        // Send response
        socket_write($client, $responseJson);
    }

    // Close only when client disconnects
    socket_close($client);
}

// Cleanup
serverLog('Shutting down...');
socket_close($socket);
if (file_exists(SOCKET_PATH)) {
    unlink(SOCKET_PATH);
}
serverLog('Server stopped');
