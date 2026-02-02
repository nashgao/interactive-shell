<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Server\ContextInterface;
use Symfony\Component\Console\Application;

/**
 * Trait for handlers that need access to the Symfony Console Application.
 *
 * Provides a standardized way to resolve the console application from
 * either Hyperf's ApplicationInterface or Symfony's Application directly.
 */
trait ConsoleApplicationAwareTrait
{
    /**
     * Resolve the console application from the context's container.
     *
     * Tries Hyperf's ApplicationInterface first, then falls back to
     * Symfony's Application directly.
     */
    protected function getConsoleApplication(ContextInterface $context): ?Application
    {
        $container = $context->getContainer();

        // Try Hyperf's ApplicationInterface first (use string to avoid dependency)
        if ($container->has('Hyperf\Contract\ApplicationInterface')) {
            $app = $container->get('Hyperf\Contract\ApplicationInterface');
            if ($app instanceof Application) {
                return $app;
            }
        }

        // Try Symfony's Application directly
        if ($container->has(Application::class)) {
            $app = $container->get(Application::class);
            if ($app instanceof Application) {
                return $app;
            }
        }

        return null;
    }
}
