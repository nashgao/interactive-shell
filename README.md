# Interactive Shell

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net/)

Interactive shell library with pluggable transports and streaming support. Build MySQL-like CLI interfaces for your PHP applications with built-in command parsing, history, formatting, and real-time message streaming capabilities.

## Documentation

- **[Visual Architecture Guide](docs/guide.md)** - ASCII diagrams, command flows, and troubleshooting

## Features

- **Standard Shell (REPL)**: Traditional request/response interactive shell with readline support
- **Streaming Shell**: Bidirectional streaming for real-time message processing (Swoole coroutines)
- **Pluggable Transports**: HTTP, Unix Socket, or implement your own via `TransportInterface`
- **Multiple Output Formats**: Table (ASCII), JSON, CSV, Vertical (MySQL `\G` style)
- **Advanced Command Parsing**: Quote handling, escape sequences, options (`--format=json`), and `\G` terminator
- **Shell Features**: Command history with persistence, configurable aliases, multi-line input
- **Client-Side Filtering**: Real-time message filtering in streaming mode
- **Built-in Commands**: Help, status, history, aliases, screen clearing

## Requirements

- PHP 8.1 or higher
- ext-swoole (coroutine-based async I/O)
- Symfony Console ^6.0|^7.0
- Guzzle HTTP ^7.0

### Optional Extensions

- `ext-readline`: Recommended for better input handling and history navigation

## Installation

```bash
composer require nashgao/interactive-shell
```

## Quick Start

### Standard Shell (Request/Response)

```php
<?php

use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\Transport\HttpTransport;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

// Create HTTP transport pointing to your backend
$transport = new HttpTransport(
    serverUrl: 'http://localhost:8080',
    timeout: 30.0,
    executePath: '/runtime/command/execute',
    pingPath: '/ping'
);

// Create shell with custom prompt and aliases
$shell = new Shell(
    transport: $transport,
    prompt: 'myapp> ',
    defaultAliases: [
        'ls' => 'list',
        'q' => 'quit',
    ]
);

// Run the interactive shell
$input = new ArgvInput();
$output = new ConsoleOutput();

$exitCode = $shell->run($input, $output);
exit($exitCode);
```

**Example Session:**
```
Connected to http://localhost:8080
Interactive Shell (type "help" for commands, "exit" to quit)

myapp> users list --format=table
+----+----------+------------------+
| ID | Username | Email            |
+----+----------+------------------+
| 1  | admin    | admin@example.com|
| 2  | john     | john@example.com |
+----+----------+------------------+
Query OK, 2 rows returned

myapp> users list --format=json
[
  {"id": 1, "username": "admin", "email": "admin@example.com"},
  {"id": 2, "username": "john", "email": "john@example.com"}
]

myapp> exit
Goodbye!
```

### Streaming Shell (Real-time Messages)

```php
<?php

use NashGao\InteractiveShell\StreamingShell;
use NashGao\InteractiveShell\Transport\SwooleSocketTransport;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

// Create streaming transport (Swoole socket example)
$transport = new SwooleSocketTransport(
    socketPath: '/var/run/mqtt-debug.sock',
    timeout: 30.0
);

// Create streaming shell
$shell = new StreamingShell(
    transport: $transport,
    prompt: 'stream> ',
    defaultAliases: []
);

// Run the streaming shell
$input = new ArgvInput();
$output = new ConsoleOutput();

$exitCode = $shell->run($input, $output);
exit($exitCode);
```

**Example Streaming Session:**
```
Connected to /var/run/mqtt-debug.sock
Streaming Shell (Swoole mode)
Commands: filter <pattern>, pause, resume, clear, exit

[2024-01-15 14:32:01] MQTT/sensor/temperature: {"value": 22.5, "unit": "C"}
[2024-01-15 14:32:05] MQTT/sensor/humidity: {"value": 65, "unit": "%"}

stream> filter sensor/temperature
Filter set: topic contains "sensor/temperature"

[2024-01-15 14:32:10] MQTT/sensor/temperature: {"value": 23.1, "unit": "C"}

stream> pause
Streaming paused

stream> stats
Messages received: 127
Filter: topic contains "sensor/temperature"
Paused: Yes

stream> resume
Streaming resumed

stream> exit

Session ended. Total messages: 127
```

## Output Formats

The shell supports four output formats that can be selected via `--format` option or `\G` terminator:

### Table Format (Default)

```php
// Command: users list --format=table
// Or simply: users list
```

**Output:**
```
+----+----------+------------------+--------+
| ID | Username | Email            | Active |
+----+----------+------------------+--------+
| 1  | admin    | admin@example.com| Yes    |
| 2  | john     | john@example.com | Yes    |
| 3  | jane     | jane@example.com | No     |
+----+----------+------------------+--------+
Query OK, 3 rows returned
```

### JSON Format

```php
// Command: users list --format=json
```

**Output:**
```json
[
  {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "active": true
  },
  {
    "id": 2,
    "username": "john",
    "email": "john@example.com",
    "active": true
  },
  {
    "id": 3,
    "username": "jane",
    "email": "jane@example.com",
    "active": false
  }
]
```

### CSV Format

```php
// Command: users list --format=csv
```

**Output:**
```
ID,Username,Email,Active
1,admin,admin@example.com,Yes
2,john,john@example.com,Yes
3,jane,jane@example.com,No
```

### Vertical Format (MySQL `\G` style)

```php
// Command: users show 1\G
// Or: users show 1 --format=vertical
```

**Output:**
```
*************************** 1. row ***************************
      ID: 1
Username: admin
   Email: admin@example.com
  Active: Yes
1 row in set
```

## Built-in Commands

These commands work offline without server connection:

| Command | Description | Example |
|---------|-------------|---------|
| `help` | Display help message with available commands | `help` |
| `exit`, `quit` | Exit the shell and save session | `exit` |
| `status` | Show connection status and session metrics | `status` |
| `clear` | Clear the terminal screen | `clear` |
| `history` | Display command history | `history` |
| `alias [name=cmd]` | Show all aliases or set a new alias | `alias ls=list` |
| `unalias <name>` | Remove an alias | `unalias ls` |

**Status Output Example:**
```
myapp> status
Shell Status:
  Session started: 2024-01-15 14:30:15
  Session duration: 00:05:23
  Commands executed: 12
  Server: http://localhost:8080
  Connected: Yes
```

## Streaming Commands

Additional commands available in `StreamingShell`:

| Command | Description | Example |
|---------|-------------|---------|
| `filter <pattern>` | Set message filter expression | `filter sensor/temperature` |
| `filter show` | Show current filter | `filter show` |
| `filter clear` | Clear filter (show all messages) | `filter clear` |
| `pause` | Pause message streaming | `pause` |
| `resume` | Resume message streaming | `resume` |
| `stats` | Show streaming statistics | `stats` |

**Filter Patterns:**
```
stream> filter sensor/temperature      # Topic contains "sensor/temperature"
stream> filter {"value": 22}          # Message contains JSON pattern
stream> filter clear                   # Clear filter
```

## Configuration

### Shell Configuration Options

```php
use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\Formatter\OutputFormat;

$shell = new Shell(
    transport: $transport,
    prompt: 'myapp> ',              // Custom prompt string
    defaultAliases: [                // Pre-configured aliases
        'ls' => 'list',
        'q' => 'quit',
        'h' => 'help',
    ]
);

// Set default output format
$shell->setOutputFormat(OutputFormat::Json);

// Change prompt dynamically
$shell->setPrompt('admin@myapp> ');
```

### Transport Configuration

#### HTTP Transport

```php
use NashGao\InteractiveShell\Transport\HttpTransport;
use GuzzleHttp\Client;

$transport = new HttpTransport(
    serverUrl: 'http://localhost:8080',
    timeout: 30.0,                           // Request timeout in seconds
    executePath: '/runtime/command/execute', // Command execution endpoint
    pingPath: '/ping',                       // Health check endpoint
    httpClient: new Client([                 // Optional custom Guzzle client
        'headers' => [
            'Authorization' => 'Bearer token123',
        ],
    ])
);
```

**Expected Server Response Format:**
```json
{
  "success": true,
  "data": [
    {"id": 1, "name": "Item 1"},
    {"id": 2, "name": "Item 2"}
  ],
  "message": "Query OK, 2 rows returned",
  "metadata": {
    "execution_time": 0.125
  }
}
```

#### Swoole Socket Transport

```php
use NashGao\InteractiveShell\Transport\SwooleSocketTransport;

$transport = new SwooleSocketTransport(
    socketPath: '/var/run/myapp.sock',
    timeout: 30.0,                    // Socket timeout
);
```

## Custom Transport Implementation

Implement the `TransportInterface` to create custom transport backends:

```php
<?php

namespace App\Shell\Transport;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Transport\TransportInterface;

final class RedisTransport implements TransportInterface
{
    private \Redis $redis;
    private bool $connected = false;

    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6379,
        private readonly float $timeout = 5.0,
    ) {
        $this->redis = new \Redis();
    }

    public function connect(): void
    {
        $this->connected = $this->redis->connect(
            $this->host,
            $this->port,
            $this->timeout
        );

        if (!$this->connected) {
            throw new \RuntimeException("Cannot connect to Redis at {$this->host}:{$this->port}");
        }
    }

    public function disconnect(): void
    {
        if ($this->connected) {
            $this->redis->close();
            $this->connected = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->redis->ping() !== false;
    }

    public function send(ParsedCommand $command): CommandResult
    {
        try {
            // Execute Redis command
            $result = $this->redis->rawCommand(
                $command->command,
                ...$command->arguments
            );

            return CommandResult::success(
                data: $result,
                message: 'OK'
            );
        } catch (\RedisException $e) {
            return CommandResult::failure(
                error: $e->getMessage()
            );
        }
    }

    public function ping(): bool
    {
        try {
            return $this->redis->ping() !== false;
        } catch (\RedisException) {
            return false;
        }
    }

    public function getInfo(): array
    {
        try {
            $info = $this->redis->info();
            return is_array($info) ? $info : [];
        } catch (\RedisException) {
            return [];
        }
    }

    public function getEndpoint(): string
    {
        return "{$this->host}:{$this->port}";
    }
}
```

**Usage:**
```php
$transport = new RedisTransport(host: 'localhost', port: 6379);
$shell = new Shell($transport, prompt: 'redis> ');
$shell->run($input, $output);
```

## Server-Side Command Handlers

Create custom command handlers for your application by implementing `CommandHandlerInterface`:

### Creating a Handler

```php
<?php

namespace App\Shell\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;

final class DatabaseHandler implements CommandHandlerInterface
{
    public function getCommand(): string
    {
        return 'db:status';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        // Access services via context
        $db = $context->get('database');

        return CommandResult::success(
            data: [
                'connected' => $db->isConnected(),
                'driver' => $db->getDriver(),
                'queries' => $db->getQueryCount(),
            ],
            message: 'Database status retrieved'
        );
    }

    public function getDescription(): string
    {
        return 'Show database connection status';
    }

    public function getUsage(): array
    {
        return ['db:status', 'db:status --verbose'];
    }
}
```

### Registering Handlers

Use `CommandRegistry` to register your handlers:

```php
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;

$registry = new CommandRegistry();

// Register individual handlers
$registry->register(new DatabaseHandler());
$registry->register(new QueueHandler());
$registry->register(new CacheHandler());

// Set a fallback handler for unknown commands
$registry->setFallbackHandler(new UnknownCommandHandler());
```

## Hyperf Framework Integration

### Automatic Setup

The library provides a `ConfigProvider` for Hyperf auto-discovery:

```bash
# Publish the configuration file
php bin/hyperf.php vendor:publish nashgao/interactive-shell
```

### Configuration

Edit `config/autoload/interactive_shell.php`:

```php
<?php

return [
    // Enable/disable shell server
    'enabled' => (bool) env('SHELL_ENABLED', true),

    // Unix socket path
    'socket_path' => env('SHELL_SOCKET_PATH', '/var/run/hyperf-shell.sock'),

    // Socket file permissions
    'socket_permissions' => 0660,

    // Register custom handlers
    'handlers' => [
        App\Shell\Handler\DatabaseHandler::class,
        App\Shell\Handler\QueueHandler::class,
    ],
];
```

### Connecting to a Running Server

Once the Hyperf server is running, connect using a client:

```php
use NashGao\InteractiveShell\Shell;
use NashGao\InteractiveShell\Transport\SwooleSocketTransport;

$transport = new SwooleSocketTransport(
    socketPath: '/var/run/hyperf-shell.sock',
    timeout: 30.0
);

$shell = new Shell($transport, prompt: 'hyperf> ');
$shell->run($input, $output);
```

### Built-in Hyperf Handlers

When running in Hyperf, these additional commands are available:

| Command | Description |
|---------|-------------|
| `config <key>` | Get configuration values using dot notation |
| `routes` | List all registered HTTP routes |
| `container <abstract>` | Inspect container bindings |
| `command <name>` | Execute Hyperf console commands |

## API Reference

### Core Classes

| Class | Description |
|-------|-------------|
| `Shell` | Standard interactive shell with request/response model |
| `StreamingShell` | Bidirectional streaming shell for real-time messages |
| `ShellParser` | Command parser with quote handling and options |
| `OutputFormatter` | Formats command results in table/JSON/CSV/vertical |
| `MessageFormatter` | Formats streaming messages with timestamps |
| `HistoryManager` | Manages command history with persistence |
| `AliasManager` | Manages command aliases |
| `ShellState` | Tracks session state and metrics |

### Transport Classes

| Class | Description |
|-------|-------------|
| `HttpTransport` | HTTP/HTTPS transport using Guzzle |
| `SwooleSocketTransport` | Swoole coroutine-based Unix socket transport (with streaming support) |
| `TransportInterface` | Interface for custom transport implementations |
| `StreamingTransportInterface` | Extended interface for streaming transports |

### Data Classes

| Class | Description |
|-------|-------------|
| `ParsedCommand` | Parsed command with arguments and options |
| `CommandResult` | Command execution result with data and metadata |
| `Message` | Streaming message with topic and payload |
| `FilterExpression` | Message filter for streaming mode |
| `OutputFormat` | Enum for output format types |

### Shell Methods

```php
// Shell execution
$shell->run(InputInterface $input, OutputInterface $output): int
$shell->executeCommand(string $command, OutputInterface $output): int

// Shell control
$shell->isRunning(): bool
$shell->stop(): void

// Configuration
$shell->setPrompt(string $prompt): void
$shell->setOutputFormat(OutputFormat $format): void

// Access components
$shell->getTransport(): TransportInterface
$shell->getAliases(): AliasManager
$shell->getHistory(): HistoryManager
```

### StreamingShell Methods

```php
// StreamingShell-specific
$shell->setFilter(FilterExpression $filter): void
$shell->getMessageCount(): int
$shell->getOutputFormatter(): OutputFormatter
```

## Advanced Features

### Multi-line Input

Use `\` at the end of a line to continue input on the next line:

```
myapp> SELECT * \
    -> FROM users \
    -> WHERE active = 1;
```

### History Navigation

- **Up/Down arrows**: Navigate through command history
- **Ctrl+R**: Reverse search through history (with readline)
- **History persistence**: Commands saved between sessions

### Alias Expansion

```
myapp> alias ll=list --format=table --verbose
Alias set: ll = list --format=table --verbose

myapp> ll users
# Expands to: list --format=table --verbose users
```

### Session Persistence

The shell automatically saves:
- Command history
- Session metrics (start time, command count, etc.)
- Connection state

## Performance Considerations

### Streaming Mode Performance

The streaming shell uses Swoole coroutines for true concurrent I/O — message receiving, display, and user input each run in their own coroutine.

### Output Format Performance

| Format | Speed | Use Case |
|--------|-------|----------|
| JSON | Fastest | Machine-readable, API integration |
| CSV | Fast | Data export, spreadsheet import |
| Table | Moderate | Human-readable terminal output |
| Vertical | Moderate | Detailed single-record inspection |

## Testing

### Test Structure

| Suite | Directory | Purpose |
|-------|-----------|---------|
| **Unit** | `tests/Unit/` | Component isolation tests |
| **Integration** | `tests/Integration/` | Cross-component interactions |
| **Specification** | `tests/Specification/` | Consumer-perspective behavior specs |
| **E2E** | `tests/E2E/` | Full system scenarios (requires Swoole) |

### Running Tests

```bash
# Run all tests
composer test

# Run with coverage report
composer test:coverage

# Run specific suite
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Integration
./vendor/bin/phpunit --testsuite=Specification

# Run single test file
./vendor/bin/phpunit tests/Unit/Parser/ShellParserTest.php

# Static analysis
composer phpstan
```

### Key Test Areas

- **Parser** (`tests/Unit/Parser/`): Command parsing, quote handling, escape sequences
- **Handlers** (`tests/Unit/Server/Handler/`): Built-in command handlers
- **Transport** (`tests/Integration/Transport/`): HTTP and socket communication
- **Formatters** (`tests/Unit/Formatter/`): Table, JSON, CSV, Vertical output
- **Shell** (`tests/Integration/`): Full shell lifecycle and command flow

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for new functionality
4. Ensure all tests pass (`composer test`)
5. Ensure PHPStan passes at level max (`composer phpstan`)
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

### Code Standards

- Follow PSR-12 coding style
- Use strict types (`declare(strict_types=1)`)
- Add type hints for all parameters and return types
- Write PHPDoc blocks for public methods
- Keep methods focused and concise

## License

This library is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

**Built with** ❤️ **by Nash Gao**
