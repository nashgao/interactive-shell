#!/usr/bin/env php
<?php

/**
 * HTTP Shell Client Example
 *
 * Connects to an HTTP server and provides an interactive shell.
 * This demonstrates using HttpTransport for cross-machine communication.
 *
 * First start the server:    php examples/http-server.php
 * Then run this client:      php examples/http-client.php
 *
 * Available commands:
 *   users list                  - List all users
 *   users list --role=admin     - Filter by role
 *   users list --active         - Filter active users
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
use NashGao\InteractiveShell\Transport\HttpTransport;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

const HTTP_URL = 'http://127.0.0.1:8765';

$output = new ConsoleOutput();

$output->writeln('<info>HTTP Shell Client</info>');
$output->writeln('<comment>Connecting to ' . HTTP_URL . '</comment>');
$output->writeln('');
$output->writeln('Available server commands:');
$output->writeln('  <info>users list</info>              - List all users');
$output->writeln('  <info>users list --role=admin</info> - Filter by role');
$output->writeln('  <info>users list --active</info>     - Filter active users');
$output->writeln('  <info>users get <id></info>          - Get user by ID');
$output->writeln('  <info>users count</info>             - Count users');
$output->writeln('  <info>ping</info>                    - Check server');
$output->writeln('  <info>echo <message></info>          - Echo back');
$output->writeln('  <info>server info</info>             - Server information');
$output->writeln('');
$output->writeln('Built-in: help, status, history, alias, clear, exit');
$output->writeln('Formats: --format=table|json|csv|vertical or \\G');
$output->writeln('');

// Create HTTP transport
$transport = new HttpTransport(
    serverUrl: HTTP_URL,
    timeout: 30.0,
    executePath: '/runtime/command/execute',
    pingPath: '/ping',
);

// Check connection before starting
try {
    $transport->connect();
} catch (\RuntimeException $e) {
    $output->writeln('<error>Error: Cannot connect to ' . HTTP_URL . '</error>');
    $output->writeln('');
    $output->writeln('Please start the server first:');
    $output->writeln('  <info>php examples/http-server.php</info>');
    $output->writeln('');
    exit(1);
}

// Create shell with custom prompt and default aliases
$shell = new Shell(
    transport: $transport,
    prompt: 'http> ',
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
