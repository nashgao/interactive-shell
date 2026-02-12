<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler;

/**
 * Contract for batch handler registration.
 *
 * Implement this interface to provide a group of related command handlers
 * as a single unit. Useful for third-party packages, logical grouping,
 * and conditional registration (return empty iterable to skip).
 */
interface HandlerProviderInterface
{
    /**
     * Return the command handlers provided by this provider.
     *
     * @return iterable<CommandHandlerInterface>
     */
    public function getHandlers(): iterable;
}
