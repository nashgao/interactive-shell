<?php

declare(strict_types=1);

/**
 * Interactive Shell configuration for Hyperf.
 *
 * Copy this file to config/autoload/interactive_shell.php in your Hyperf project,
 * or use `php bin/hyperf.php vendor:publish nash-gao/interactive-shell`.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Shell Server
    |--------------------------------------------------------------------------
    |
    | When disabled, the shell process will not start with Hyperf.
    | This is useful for production environments where shell access
    | should be restricted.
    |
    */
    'enabled' => (bool) env('SHELL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Unix Socket Path
    |--------------------------------------------------------------------------
    |
    | Path to the Unix socket file for shell connections. This should be
    | in a location accessible to the shell client but protected from
    | unauthorized access.
    |
    | Common locations:
    | - /var/run/hyperf-shell.sock (system services)
    | - /tmp/hyperf-shell.sock (development)
    | - BASE_PATH . '/runtime/shell.sock' (project-local)
    |
    */
    'socket_path' => env('SHELL_SOCKET_PATH', '/var/run/hyperf-shell.sock'),

    /*
    |--------------------------------------------------------------------------
    | Socket File Permissions
    |--------------------------------------------------------------------------
    |
    | Unix permissions for the socket file. Default 0660 allows read/write
    | for owner and group only.
    |
    | Common values:
    | - 0660: Owner and group (recommended for security)
    | - 0666: World-accessible (development only)
    | - 0600: Owner only (strictest)
    |
    */
    'socket_permissions' => 0660,

    /*
    |--------------------------------------------------------------------------
    | Custom Command Handlers
    |--------------------------------------------------------------------------
    |
    | Register additional command handlers here. Each handler must implement
    | NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface.
    |
    | Handlers can be resolved from the container if registered, or
    | instantiated directly if they have no constructor dependencies.
    |
    | Example:
    | 'handlers' => [
    |     App\Shell\Handlers\DatabaseHandler::class,
    |     App\Shell\Handlers\QueueHandler::class,
    | ],
    |
    */
    'handlers' => [],
];
