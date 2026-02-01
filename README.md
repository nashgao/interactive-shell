# Space Platform Contracts

Shared contracts and interfaces for Space Platform packages.

## Installation

```bash
composer require space-platform/contracts
```

## Usage

```php
use SpacePlatform\Contracts\Forge\ForgeClientInterface;
use SpacePlatform\Contracts\Monitor\HealthCheckableInterface;

class MyForgeClient implements ForgeClientInterface
{
    // Implementation
}
```

## Namespaces

- `SpacePlatform\Contracts\Forge\` - Forge runtime contracts (13 interfaces/classes)
- `SpacePlatform\Contracts\Monitor\` - Monitoring contracts (10 interfaces)

## License

Proprietary - Space Platform PTY LTD
