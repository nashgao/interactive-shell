<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load Hyperf stub interfaces for testing when Hyperf is not installed.
// This allows PHPUnit to create mocks for Hyperf interfaces/classes.
if (!interface_exists('Hyperf\Contract\ConfigInterface')) {
    require_once __DIR__ . '/Stubs/Hyperf/Contract/ConfigInterface.php';
}

if (!class_exists('Hyperf\Process\AbstractProcess')) {
    require_once __DIR__ . '/Stubs/Hyperf/Process/AbstractProcess.php';
}

if (!class_exists('Hyperf\Process\Annotation\Process')) {
    require_once __DIR__ . '/Stubs/Hyperf/Process/Annotation/Process.php';
}
