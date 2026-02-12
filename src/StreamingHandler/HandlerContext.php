<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\StreamingHandler;

use NashGao\InteractiveShell\Transport\StreamingTransportInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract base context passed to streaming shell command handlers.
 *
 * Provides access to common shell components. Protocol-specific
 * implementations should extend this class to add domain-specific
 * components (filter, history, stats, formatter, etc.).
 */
abstract readonly class HandlerContext
{
    public function __construct(
        public OutputInterface $output,
        public StreamingTransportInterface $transport,
    ) {}
}
