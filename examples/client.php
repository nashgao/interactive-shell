#!/usr/bin/env php
<?php

/**
 * Unix Socket Shell Client Example
 *
 * Connects to the Unix socket server and provides an interactive shell.
 * This demonstrates real IPC using the SwooleSocketTransport.
 *
 * First start the server:    php examples/server.php
 * Then run this client:      php examples/client.php
 *
 * Available commands:
 *   users list                  - List all users
 *   users list --role=admin     - Filter by role
 *   users get <id>              - Get user by ID
 *   users count                 - Count users
 *   ping                        - Check server
 *   echo <message>              - Echo back
 *   server info                 - Server information
 *   server time                 - Server time
 *
 * Built-in shell commands:
 *   help                        - Show all commands
 *   status                      - Show shell status
 *   history                     - Show command history
 *   alias <name>=<cmd>          - Create alias
 *   clear                       - Clear screen
 *   exit                        - Exit shell
 *
 * Output formats:
 *   --format=table              - Table format (default)
 *   --format=json               - JSON format
 *   --format=csv                - CSV format
 *   \G                          - Vertical format
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\Transport\SwooleSocketTransport;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

const SOCKET_PATH = '/tmp/interactive-shell.sock';

$output = new ConsoleOutput();

// Check if socket exists
if (!file_exists(SOCKET_PATH)) {
    $output->writeln('<error>Error: Socket not found at ' . SOCKET_PATH . '</error>');
    $output->writeln('');
    $output->writeln('Please start the server first:');
    $output->writeln('  <info>php examples/server.php</info>');
    $output->writeln('');
    exit(1);
}

$output->writeln('<info>Unix Socket Shell Client</info>');
$output->writeln('<comment>Connecting to ' . SOCKET_PATH . '</comment>');
$output->writeln('');
$output->writeln('Available server commands:');
$output->writeln('  <info>users list</info>              - List all users');
$output->writeln('  <info>users list --role=admin</info> - Filter by role');
$output->writeln('  <info>users get <id></info>          - Get user by ID');
$output->writeln('  <info>users count</info>             - Count users');
$output->writeln('  <info>ping</info>                    - Check server');
$output->writeln('  <info>echo <message></info>          - Echo back');
$output->writeln('  <info>server info</info>             - Server information');
$output->writeln('');
$output->writeln('Built-in: help, status, history, alias, clear, exit');
$output->writeln('Formats: --format=table|json|csv|vertical or \\G');
$output->writeln('');

// Create transport with Swoole socket
$transport = new SwooleSocketTransport(
    socketPath: SOCKET_PATH,
    timeout: 30.0,
);

// Create shell with custom prompt and default aliases
$shell = new Shell(
    transport: $transport,
    prompt: 'socket> ',
    defaultAliases: [
        'u' => 'users',
        'll' => 'users list',
        'p' => 'ping',
        'si' => 'server info',
    ],
);

// Run the interactive shell
$exitCode = $shell->run(new ArgvInput(), $output);

exit($exitCode);
