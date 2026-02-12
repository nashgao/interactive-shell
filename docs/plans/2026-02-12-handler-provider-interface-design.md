# HandlerProviderInterface Design

## Problem

The interactive shell has three handler registration paths: hardcoded built-ins, config-listed class names, and attribute-based auto-discovery. There is no contract for batch handler registration, which limits three use cases:

1. **Third-party extensibility** -- packages cannot ship a bundle of related handlers as a single unit
2. **Grouping related handlers** -- users must list handlers individually in config rather than organizing them logically
3. **Conditional registration** -- no clean way to register handlers based on runtime conditions (environment, feature flags, available services)

## Design

### Interface

A single interface with one method:

```php
namespace NashGao\InteractiveShell\Server\Handler;

interface HandlerProviderInterface
{
    /** @return iterable<CommandHandlerInterface> */
    public function getHandlers(): iterable;
}
```

Providers control what they return, including nothing (empty iterable) for conditional registration. No `isEnabled()` method -- returning empty is sufficient and keeps the contract minimal.

### Discovery Attribute

A companion attribute for auto-discovery, mirroring the existing `AsShellHandler` pattern:

```php
namespace NashGao\InteractiveShell\Server\Handler;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsHandlerProvider {}
```

Providers can be registered two ways:
- **Explicit**: list class names in `interactive_shell.providers` config
- **Implicit**: annotate with `#[AsHandlerProvider]`, discovered alongside `#[AsShellHandler]`

### Registration Order

Providers slot into `ShellProcess::createRegistry()` between built-ins and individual handlers:

1. Built-in handlers (unchanged)
2. **Config-listed providers** (`interactive_shell.providers`) -- new
3. **Auto-discovered providers** (`#[AsHandlerProvider]`) -- new
4. Config-listed handler classes (`interactive_shell.handlers`) -- unchanged
5. Auto-discovered `#[AsShellHandler]` handlers -- unchanged
6. Fallback handler (unchanged)

Providers run before individual handlers so that individual handler config can override a provider's handler for the same command. The existing "skip if already registered" logic handles conflicts.

### Config

One new key added to `publish/interactive_shell.php`:

```php
return [
    'enabled' => true,
    'socket_path' => '/var/run/hyperf-shell.sock',
    'providers' => [],          // new
    'handlers' => [],
    'handler_discovery' => [
        'enabled' => true,
        'namespaces' => ['App\\Shell\\'],
    ],
];
```

### Discovery Changes

`HandlerDiscovery` gains a `discoverProviders()` method that mirrors `discover()` -- same classmap scanning, same namespace filtering, but matches `HandlerProviderInterface` + `#[AsHandlerProvider]` instead of `CommandHandlerInterface` + `#[AsShellHandler]`.

### Provider Resolution

Same pattern as handler resolution: try the DI container first, fall back to direct instantiation. Container resolution allows providers to receive injected dependencies.

## Usage Examples

### Grouping related handlers

```php
#[AsHandlerProvider]
final class DatabaseHandlerProvider implements HandlerProviderInterface
{
    public function __construct(
        private readonly ConnectionInterface $db
    ) {}

    public function getHandlers(): iterable
    {
        yield new TableListHandler($this->db);
        yield new QueryHandler($this->db);
        yield new MigrationStatusHandler($this->db);
    }
}
```

### Conditional registration

```php
#[AsHandlerProvider]
final class DebugHandlerProvider implements HandlerProviderInterface
{
    public function __construct(
        private readonly ConfigInterface $config
    ) {}

    public function getHandlers(): iterable
    {
        if ($this->config->get('app_env') !== 'dev') {
            return;
        }

        yield new XdebugHandler();
        yield new DumpServerHandler();
    }
}
```

## File Changes

### New files (2)

- `src/Server/Handler/HandlerProviderInterface.php` -- the interface
- `src/Server/Handler/AsHandlerProvider.php` -- the discovery attribute

### Modified files (3)

- `src/Server/Hyperf/HandlerDiscovery.php` -- add `discoverProviders()` method
- `src/Server/Hyperf/ShellProcess.php` -- integrate provider resolution into `createRegistry()`
- `publish/interactive_shell.php` -- add `providers` config key

### Not changed

- `CommandHandlerInterface`, `CommandRegistry`, `AbstractCommandHandler` -- untouched
- Existing handler registration paths -- fully preserved
- Built-in handlers -- stay hardcoded

## Testing

- Provider's handlers get registered and are callable through the registry
- Auto-discovered providers are found and resolved
- Empty provider (conditional skip) registers nothing
- Individual handler config overrides a provider's handler for the same command
