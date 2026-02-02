#!/usr/bin/env php
<?php

/**
 * Swoole Streaming Server Example
 *
 * A streaming server that broadcasts real-time events to connected clients.
 * This demonstrates the StreamingShell's ability to receive asynchronous
 * messages while maintaining an interactive command interface.
 *
 * Requirements:
 *   - ext-swoole: Required for coroutine-based async I/O
 *
 * Run with: php examples/streaming-server.php
 *
 * Then connect with: php examples/streaming-client.php
 *
 * Protocol:
 *   Subscribe:    {"type":"subscribe"}\n
 *   Unsubscribe:  {"type":"unsubscribe"}\n
 *   Command:      {"type":"command","command":"...","arguments":[...]}\n
 *   Message:      {"type":"message","topic":"...","payload":"...","timestamp":"..."}\n
 */

declare(strict_types=1);

// Check for Swoole extension
if (!extension_loaded('swoole')) {
    echo "Error: Swoole extension required for streaming server.\n";
    echo "\n";
    echo "Install Swoole:\n";
    echo "  pecl install swoole\n";
    echo "\n";
    echo "Or for development without streaming, use:\n";
    echo "  php examples/server.php (standard socket server)\n";
    echo "\n";
    exit(1);
}

use Swoole\Coroutine;
use Swoole\Coroutine\Server;
use Swoole\Coroutine\Server\Connection;

const SOCKET_PATH = '/tmp/interactive-shell-streaming.sock';

/** @var array<int, Connection> */
$subscribers = [];

/** @var array<string, array{description: string, min: float, max: float, unit: string}> */
$sensors = [
    'sensor/temperature' => ['description' => 'Temperature sensor', 'min' => 18.0, 'max' => 28.0, 'unit' => 'C'],
    'sensor/humidity' => ['description' => 'Humidity sensor', 'min' => 40.0, 'max' => 80.0, 'unit' => '%'],
    'sensor/pressure' => ['description' => 'Pressure sensor', 'min' => 990.0, 'max' => 1030.0, 'unit' => 'hPa'],
    'sensor/light' => ['description' => 'Light sensor', 'min' => 0.0, 'max' => 1000.0, 'unit' => 'lux'],
];

$startTime = time();
$messagesSent = 0;

/**
 * Generate a simulated sensor reading.
 *
 * @param array{description: string, min: float, max: float, unit: string} $config
 * @return array{value: float, unit: string}
 */
function generateReading(array $config): array
{
    $value = $config['min'] + (mt_rand() / mt_getrandmax()) * ($config['max'] - $config['min']);
    return [
        'value' => round($value, 2),
        'unit' => $config['unit'],
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
 * Handle incoming commands.
 *
 * @param array<string, mixed> $request
 * @return array<string, mixed>
 */
function handleRequest(array $request): array
{
    global $sensors, $startTime, $messagesSent, $subscribers;

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

    return match ($command) {
        'sensors' => [
            'success' => true,
            'data' => array_map(fn ($key, $config) => [
                'topic' => $key,
                'description' => $config['description'],
                'unit' => $config['unit'],
            ], array_keys($sensors), $sensors),
            'message' => sprintf('Found %d sensors', count($sensors)),
        ],
        'status' => [
            'success' => true,
            'data' => [
                'uptime' => formatUptime(time() - $startTime),
                'subscribers' => count($subscribers),
                'messages_sent' => $messagesSent,
                'sensors' => count($sensors),
            ],
        ],
        'ping' => [
            'success' => true,
            'data' => ['message' => 'pong', 'time' => date('c')],
        ],
        default => [
            'success' => false,
            'error' => "Unknown command: {$command}. Available: sensors, status, ping",
        ],
    };
}

/**
 * Broadcast a message to all subscribers.
 *
 * @param array<string, mixed> $message
 */
function broadcast(array $message): void
{
    global $subscribers, $messagesSent;

    $json = json_encode($message) . "\n";
    $disconnected = [];

    foreach ($subscribers as $fd => $conn) {
        try {
            $conn->send($json);
            ++$messagesSent;
        } catch (\Throwable) {
            $disconnected[] = $fd;
        }
    }

    // Remove disconnected subscribers
    foreach ($disconnected as $fd) {
        unset($subscribers[$fd]);
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

Coroutine\run(function () use (&$subscribers, $sensors): void {
    // Cleanup stale socket
    if (file_exists(SOCKET_PATH)) {
        unlink(SOCKET_PATH);
    }

    // Create server
    $server = new Server('unix:' . SOCKET_PATH, 0, false);

    // Set socket permissions after creation
    Coroutine::create(function (): void {
        // Wait for socket to be created
        $attempts = 0;
        while (!file_exists(SOCKET_PATH) && $attempts < 10) {
            Coroutine::sleep(0.1);
            $attempts++;
        }
        if (file_exists(SOCKET_PATH)) {
            chmod(SOCKET_PATH, 0777);
        }
    });

    echo "\n";
    echo "========================================================\n";
    echo "           Swoole Streaming Server Started              \n";
    echo "========================================================\n";
    echo "  Socket: " . SOCKET_PATH . "\n";
    echo "  PID:    " . getmypid() . "\n";
    echo "========================================================\n";
    echo "  Commands: sensors, status, ping                       \n";
    echo "  Streaming: subscribe, unsubscribe                     \n";
    echo "========================================================\n";
    echo "  Connect with: php examples/streaming-client.php       \n";
    echo "  Press Ctrl+C to stop the server                       \n";
    echo "========================================================\n\n";

    serverLog('Server listening...');

    // Start sensor broadcast coroutine
    Coroutine::create(function () use ($sensors): void {
        $sensorKeys = array_keys($sensors);
        while (true) {
            // Broadcast a random sensor reading every 2-5 seconds
            Coroutine::sleep(mt_rand(2000, 5000) / 1000);

            $topic = $sensorKeys[array_rand($sensorKeys)];
            $reading = generateReading($sensors[$topic]);

            $message = [
                'type' => 'message',
                'topic' => $topic,
                'payload' => json_encode($reading),
                'timestamp' => date('c'),
            ];

            broadcast($message);
        }
    });

    // Handle connections
    $server->handle(function (Connection $conn) use (&$subscribers): void {
        $fd = spl_object_id($conn);
        serverLog("Client connected (fd={$fd})");

        while (true) {
            $data = $conn->recv();
            if ($data === false || $data === '') {
                serverLog("Client disconnected (fd={$fd})");
                unset($subscribers[$fd]);
                break;
            }

            $lines = explode("\n", trim($data));
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                serverLog("Received: {$line}");

                try {
                    /** @var array<string, mixed> $request */
                    $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    $response = json_encode([
                        'success' => false,
                        'error' => "Invalid JSON: {$e->getMessage()}",
                    ]) . "\n";
                    $conn->send($response);
                    continue;
                }

                $type = $request['type'] ?? '';

                // Handle subscribe/unsubscribe
                if ($type === 'subscribe') {
                    $subscribers[$fd] = $conn;
                    serverLog("Client subscribed (fd={$fd})");
                    $conn->send(json_encode(['success' => true, 'subscribed' => true]) . "\n");
                    continue;
                }

                if ($type === 'unsubscribe') {
                    unset($subscribers[$fd]);
                    serverLog("Client unsubscribed (fd={$fd})");
                    $conn->send(json_encode(['success' => true, 'subscribed' => false]) . "\n");
                    continue;
                }

                // Handle other requests
                $response = handleRequest($request);
                $responseJson = json_encode($response) . "\n";
                serverLog("Sent: " . trim($responseJson));
                $conn->send($responseJson);
            }
        }
    });

    // Start the server
    $server->start();
});

// Cleanup
serverLog('Shutting down...');
if (file_exists(SOCKET_PATH)) {
    unlink(SOCKET_PATH);
}
serverLog('Server stopped');
