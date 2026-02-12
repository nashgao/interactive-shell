<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler;

use Attribute;

/**
 * Marks a class as an auto-discoverable handler provider.
 *
 * Classes annotated with this attribute will be found during handler
 * discovery, eliminating the need for manual config entries.
 *
 * The class must implement HandlerProviderInterface.
 *
 * Usage:
 *   #[AsHandlerProvider]
 *   class MyProvider implements HandlerProviderInterface { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsHandlerProvider {}
