<?php

declare(strict_types=1);

namespace Hyperf\Process;

use Psr\Container\ContainerInterface;

/**
 * Stub class for testing when Hyperf is not installed.
 *
 * This abstract class mimics Hyperf\Process\AbstractProcess to allow
 * extending classes to be tested without the full Hyperf framework.
 */
abstract class AbstractProcess
{
    public string $name = '';

    public function __construct(
        protected ContainerInterface $container
    ) {}

    abstract public function handle(): void;

    public function isEnable(mixed $server): bool
    {
        return true;
    }
}
