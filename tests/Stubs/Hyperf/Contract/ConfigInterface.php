<?php

declare(strict_types=1);

namespace Hyperf\Contract;

/**
 * Stub interface for testing when Hyperf is not installed.
 *
 * This interface mimics Hyperf\Contract\ConfigInterface to allow
 * PHPUnit to create mocks for tests that depend on Hyperf classes.
 */
interface ConfigInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function set(string $key, mixed $value): void;
}
