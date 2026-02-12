<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Server\Handler\AsHandlerProvider;
use NashGao\InteractiveShell\Server\Handler\HandlerProviderInterface;

/**
 * Fixture: provider that returns no handlers (conditional skip scenario).
 */
#[AsHandlerProvider]
final class EmptyProvider implements HandlerProviderInterface
{
    public function getHandlers(): iterable
    {
        return [];
    }
}
