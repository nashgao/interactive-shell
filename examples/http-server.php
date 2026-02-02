#!/usr/bin/env php
<?php

/**
 * Simple HTTP Server Example
 *
 * A basic HTTP server that handles interactive shell commands.
 * This demonstrates how to set up a backend that works with HttpTransport.
 *
 * Run with: php examples/http-server.php
 *
 * Then connect with: php examples/http-client.php
 *
 * API Endpoints:
 *   GET  /ping                           - Health check
 *   POST /runtime/command/execute        - Execute command
 *   GET  /runtime/health                 - Server health info
 *
 * Expected request format:
 *   POST /runtime/command/execute
 *   Content-Type: application/json
 *   {"command": "users", "args": ["list", "--role=admin"]}
 *
 * Response format:
 *   {"success": true, "data": [...], "message": "...", "metadata": {...}}
 */

declare(strict_types=1);

const HTTP_HOST = '127.0.0.1';
const HTTP_PORT = 8765;

/** @var array<int, array{id: int, name: string, email: string, role: string, active: bool}> */
$users = [
    ['id' => 1, 'name' => 'Alice Johnson', 'email' => 'alice@example.com', 'role' => 'admin', 'active' => true],
    ['id' => 2, 'name' => 'Bob Smith', 'email' => 'bob@example.com', 'role' => 'user', 'active' => true],
    ['id' => 3, 'name' => 'Carol White', 'email' => 'carol@example.com', 'role' => 'user', 'active' => false],
    ['id' => 4, 'name' => 'David Brown', 'email' => 'david@example.com', 'role' => 'moderator', 'active' => true],
    ['id' => 5, 'name' => 'Eve Davis', 'email' => 'eve@example.com', 'role' => 'admin', 'active' => true],
];

$startTime = time();
$requestCount = 0;

/**
 * Parse command arguments from request.
 *
 * @param array<int, string> $args
 * @return array{arguments: array<int, string>, options: array<string, string|bool>}
 */
function parseArgs(array $args): array
{
    $arguments = [];
    $options = [];

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--')) {
            $option = substr($arg, 2);
            if (str_contains($option, '=')) {
                [$key, $value] = explode('=', $option, 2);
                $options[$key] = $value;
            } else {
                $options[$option] = true;
            }
        } elseif (str_starts_with($arg, '-')) {
            $options[substr($arg, 1)] = true;
        } else {
            $arguments[] = $arg;
        }
    }

    return ['arguments' => $arguments, 'options' => $options];
}

/**
 * Handle 'users' command.
 *
 * @param array<int, string> $arguments
 * @param array<string, string|bool> $options
 * @param array<int, array{id: int, name: string, email: string, role: string, active: bool}> $users
 * @return array<string, mixed>
 */
function handleUsers(array $arguments, array $options, array $users): array
{
    $subcommand = $arguments[0] ?? 'list';

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
 * @param array<string, string|bool> $options
 * @param array<int, array{id: int, name: string, email: string, role: string, active: bool}> $users
 * @return array<string, mixed>
 */
function handleUsersList(array $options, array $users): array
{
    $result = $users;

    // Filter by role
    if (isset($options['role']) && is_string($options['role'])) {
        $role = $options['role'];
        $result = array_values(array_filter($result, fn ($u) => $u['role'] === $role));
    }

    // Filter by active status
    if (isset($options['active'])) {
        $result = array_values(array_filter($result, fn ($u) => $u['active']));
    }

    if (isset($options['inactive'])) {
        $result = array_values(array_filter($result, fn ($u) => !$u['active']));
    }

    return [
        'success' => true,
        'data' => $result,
        'message' => sprintf('Found %d user(s)', count($result)),
        'metadata' => [
            'execution_time' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4),
        ],
    ];
}

/**
 * Handle 'users get' command.
 *
 * @param array<int, string> $arguments
 * @param array<int, array{id: int, name: string, email: string, role: string, active: bool}> $users
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
            'server' => 'http-server',
        ],
        'message' => 'Server is responding',
    ];
}

/**
 * Handle 'server' command.
 *
 * @param array<int, string> $arguments
 * @return array<string, mixed>
 */
function handleServer(array $arguments): array
{
    global $startTime, $requestCount;

    $subcommand = $arguments[0] ?? 'info';
    $uptimeSeconds = time() - $startTime;

    return match ($subcommand) {
        'info' => [
            'success' => true,
            'data' => [
                'name' => 'HTTP Example Server',
                'version' => '1.0.0',
                'uptime' => formatUptime($uptimeSeconds),
                'requests' => $requestCount,
                'memory' => formatBytes(memory_get_usage(true)),
                'pid' => getmypid(),
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
 * Handle 'echo' command.
 *
 * @param array<int, string> $arguments
 * @return array<string, mixed>
 */
function handleEcho(array $arguments): array
{
    $message = implode(' ', $arguments);
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

    return "{$hours} hours, {$remainingMinutes} min";
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
 * Send JSON response.
 *
 * @param resource $client
 * @param array<string, mixed> $data
 * @param int $statusCode
 */
function sendJsonResponse($client, array $data, int $statusCode = 200): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $statusText = match ($statusCode) {
        200 => 'OK',
        400 => 'Bad Request',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error',
        default => 'Unknown',
    };

    $response = "HTTP/1.1 {$statusCode} {$statusText}\r\n";
    $response .= "Content-Type: application/json\r\n";
    $response .= "Content-Length: " . strlen($json) . "\r\n";
    $response .= "Connection: close\r\n";
    $response .= "\r\n";
    $response .= $json;

    fwrite($client, $response);
}

/**
 * Log a message with timestamp.
 */
function serverLog(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
}

/**
 * Route the command to appropriate handler.
 *
 * @param string $command
 * @param array<int, string> $args
 * @return array<string, mixed>
 */
function routeCommand(string $command, array $args): array
{
    global $users;

    $parsed = parseArgs($args);
    $arguments = $parsed['arguments'];
    $options = $parsed['options'];

    return match ($command) {
        'users' => handleUsers($arguments, $options, $users),
        'ping' => handlePing(),
        'echo' => handleEcho($arguments),
        'server' => handleServer($arguments),
        default => [
            'success' => false,
            'error' => "Unknown command: {$command}. Available: users, ping, echo, server",
        ],
    };
}

// ============================================================================
// Main HTTP Server
// ============================================================================

$socket = stream_socket_server(
    "tcp://" . HTTP_HOST . ":" . HTTP_PORT,
    $errno,
    $errstr
);

if ($socket === false) {
    echo "Error: Failed to create server: {$errstr} ({$errno})\n";
    exit(1);
}

echo "\n";
echo "========================================================\n";
echo "            HTTP Example Server Started                 \n";
echo "========================================================\n";
echo "  URL:    http://" . HTTP_HOST . ":" . HTTP_PORT . "\n";
echo "  PID:    " . getmypid() . "\n";
echo "========================================================\n";
echo "  Commands: users, ping, echo, server                   \n";
echo "========================================================\n";
echo "  Connect with: php examples/http-client.php            \n";
echo "  Press Ctrl+C to stop the server                       \n";
echo "========================================================\n\n";

serverLog('Server listening on http://' . HTTP_HOST . ':' . HTTP_PORT);

// Handle shutdown gracefully
$shutdown = false;
pcntl_signal(SIGINT, function () use (&$shutdown) {
    $shutdown = true;
});
pcntl_signal(SIGTERM, function () use (&$shutdown) {
    $shutdown = true;
});

while (!$shutdown) {
    pcntl_signal_dispatch();

    // Non-blocking accept with timeout
    $read = [$socket];
    $write = $except = null;
    $changed = @stream_select($read, $write, $except, 0, 100000);

    if ($changed === false || $changed === 0) {
        continue;
    }

    $client = @stream_socket_accept($socket, 0);
    if ($client === false) {
        continue;
    }

    ++$requestCount;

    // Read request
    $request = '';
    $contentLength = 0;
    $headers = [];

    // Read headers
    while (($line = fgets($client)) !== false) {
        $line = trim($line);
        if ($line === '') {
            break;
        }

        if (str_starts_with($line, 'Content-Length:')) {
            $contentLength = (int) trim(substr($line, 15));
        }

        if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
            $headers[strtolower($matches[1])] = $matches[2];
        }

        if (empty($request)) {
            $request = $line;
        }
    }

    // Read body if present
    $body = '';
    if ($contentLength > 0) {
        $body = fread($client, $contentLength);
    }

    // Parse request line
    if (preg_match('/^(GET|POST|PUT|DELETE)\s+([^\s]+)\s+HTTP/', $request, $matches)) {
        $method = $matches[1];
        $path = $matches[2];
    } else {
        sendJsonResponse($client, ['success' => false, 'error' => 'Invalid request'], 400);
        fclose($client);
        continue;
    }

    serverLog("{$method} {$path}");

    // Route request
    if ($method === 'GET' && $path === '/ping') {
        sendJsonResponse($client, ['success' => true, 'message' => 'pong']);
    } elseif ($method === 'GET' && $path === '/runtime/health') {
        $uptimeSeconds = time() - $startTime;
        sendJsonResponse($client, [
            'status' => 'healthy',
            'uptime' => formatUptime($uptimeSeconds),
            'requests' => $requestCount,
            'memory' => formatBytes(memory_get_usage(true)),
        ]);
    } elseif ($method === 'POST' && $path === '/runtime/command/execute') {
        try {
            /** @var array{command?: string, args?: array<int, string>} $data */
            $data = json_decode($body ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            sendJsonResponse($client, [
                'success' => false,
                'error' => "Invalid JSON: {$e->getMessage()}",
            ], 400);
            fclose($client);
            continue;
        }

        $command = $data['command'] ?? '';
        $args = $data['args'] ?? [];

        if (!is_string($command) || $command === '') {
            sendJsonResponse($client, [
                'success' => false,
                'error' => 'Missing or invalid command',
            ], 400);
            fclose($client);
            continue;
        }

        $result = routeCommand($command, $args);
        $statusCode = ($result['success'] ?? false) ? 200 : 400;
        sendJsonResponse($client, $result, $statusCode);
    } else {
        sendJsonResponse($client, [
            'success' => false,
            'error' => "Not found: {$method} {$path}",
        ], 404);
    }

    fclose($client);
}

// Cleanup
serverLog('Shutting down...');
fclose($socket);
serverLog('Server stopped');
