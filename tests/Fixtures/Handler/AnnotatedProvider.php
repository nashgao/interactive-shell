<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Fixtures\Handler;

use NashGao\InteractiveShell\Server\Handler\AsHandlerProvider;
use NashGao\InteractiveShell\Server\Handler\HandlerProviderInterface;

/**
 * Fixture: annotated handler provider for discovery tests.
 */
#[AsHandlerProvider]
final class AnnotatedProvider implements HandlerProviderInterface
{
    public function getHandlers(): iterable
    {
        yield new AnnotatedHandler();
    }
}
