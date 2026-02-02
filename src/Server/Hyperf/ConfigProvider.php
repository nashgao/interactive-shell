<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Hyperf;

/**
 * Hyperf ConfigProvider for auto-discovery of interactive shell components.
 *
 * This class is automatically detected by Hyperf's component discovery
 * mechanism via composer.json's extra.hyperf.config entry.
 */
final class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : getcwd();

        return [
            'processes' => [
                ShellProcess::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'Interactive shell configuration file.',
                    'source' => dirname(__DIR__, 3) . '/config/interactive_shell.php',
                    'destination' => $basePath . '/config/autoload/interactive_shell.php',
                ],
            ],
        ];
    }
}
