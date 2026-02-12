<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler;

use Attribute;

/**
 * Marks a class as an auto-discoverable shell command handler.
 *
 * Classes annotated with this attribute will be found by ShellProcess
 * during handler discovery, eliminating the need for manual config entries.
 *
 * The class must implement CommandHandlerInterface.
 *
 * Usage:
 *   #[AsShellHandler]
 *   class MyHandler implements CommandHandlerInterface { ... }
 *
 *   #[AsShellHandler(command: 'db:status', description: 'Show DB status')]
 *   class DatabaseStatusHandler extends AbstractCommandHandler { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsShellHandler
{
    public function __construct(
        public readonly ?string $command = null,
        public readonly ?string $description = null,
    ) {}
}
