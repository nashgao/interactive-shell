#!/usr/bin/env php
<?php

/**
 * StreamingShell Client Example
 *
 * Connects to the streaming server and provides a bidirectional interactive shell.
 * This demonstrates real-time message reception while maintaining command input.
 *
 * Requirements:
 *   - ext-swoole: Recommended for best performance (falls back to polling mode)
 *
 * First start the server:    php examples/streaming-server.php
 * Then run this client:      php examples/streaming-client.php
 *
 * Available commands:
 *   sensors                   - List available sensors
 *   status                    - Server status
 *   ping                      - Check server
 *
 * Streaming commands:
 *   filter <pattern>          - Filter messages by topic/content
 *   filter show               - Show current filter
 *   filter clear              - Clear filter (show all)
 *   pause                     - Pause message display
 *   resume                    - Resume message display
 *   stats                     - Show streaming statistics
 *
 * Built-in shell commands:
 *   help                      - Show all commands
 *   status                    - Show shell status
 *   history                   - Show command history
 *   clear                     - Clear screen
 *   exit                      - Exit shell
 *
 * Output formats:
 *   --format=table            - Table format (default)
 *   --format=json             - JSON format
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NashGao\InteractiveShell\StreamingShell;
use NashGao\InteractiveShell\Transport\UnixSocketTransport;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

const SOCKET_PATH = '/tmp/interactive-shell-streaming.sock';

$output = new ConsoleOutput();

// Check if socket exists
if (!file_exists(SOCKET_PATH)) {
    $output->writeln('<error>Error: Streaming socket not found at ' . SOCKET_PATH . '</error>');
    $output->writeln('');
    $output->writeln('Please start the streaming server first:');
    $output->writeln('  <info>php examples/streaming-server.php</info>');
    $output->writeln('');
    $output->writeln('<comment>Note: The streaming server requires ext-swoole.</comment>');
    $output->writeln('');
    exit(1);
}

// Check for Swoole (optional but recommended)
if (!extension_loaded('swoole')) {
    $output->writeln('<comment>Note: Swoole not detected. Running in polling mode.</comment>');
    $output->writeln('<comment>For best streaming performance, install: pecl install swoole</comment>');
    $output->writeln('');
}

$output->writeln('<info>Streaming Shell Client</info>');
$output->writeln('<comment>Connecting to ' . SOCKET_PATH . '</comment>');
$output->writeln('');
$output->writeln('Server commands:');
$output->writeln('  <info>sensors</info>                - List available sensors');
$output->writeln('  <info>status</info>                 - Server status');
$output->writeln('  <info>ping</info>                   - Check server');
$output->writeln('');
$output->writeln('Streaming commands:');
$output->writeln('  <info>filter sensor/temperature</info> - Filter by topic');
$output->writeln('  <info>filter clear</info>              - Show all messages');
$output->writeln('  <info>pause</info>                     - Pause streaming');
$output->writeln('  <info>resume</info>                    - Resume streaming');
$output->writeln('  <info>stats</info>                     - Streaming statistics');
$output->writeln('');
$output->writeln('Built-in: help, clear, exit');
$output->writeln('');

// Create streaming transport with Unix socket
$transport = new UnixSocketTransport(
    socketPath: SOCKET_PATH,
    timeout: 0.0,  // No timeout for streaming
);

// Create streaming shell with custom prompt
$shell = new StreamingShell(
    transport: $transport,
    prompt: 'stream> ',
    defaultAliases: [
        's' => 'sensors',
        'st' => 'status',
        'p' => 'ping',
        'f' => 'filter',
    ],
);

// Run the streaming shell
$exitCode = $shell->run(new ArgvInput(), $output);

exit($exitCode);
