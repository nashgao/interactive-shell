<?php

declare(strict_types=1);

namespace Hyperf\Process\Annotation;

use Attribute;

/**
 * Stub attribute for testing when Hyperf is not installed.
 *
 * This attribute mimics Hyperf\Process\Annotation\Process to allow
 * annotated classes to be loaded without the full Hyperf framework.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Process
{
    public function __construct(
        public int $nums = 1,
        public string $name = '',
        public bool $enableCoroutine = true
    ) {}
}
