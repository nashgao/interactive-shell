<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;

/**
 * Handler for reading configuration values.
 */
final class ConfigHandler implements CommandHandlerInterface
{
    public function getCommand(): string
    {
        return 'config';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $key = $command->getArgument(0);

        if ($key === null || $key === '') {
            return $this->listTopLevelKeys($context);
        }

        return $this->getConfigValue($context, is_scalar($key) ? (string) $key : '');
    }

    private function listTopLevelKeys(ContextInterface $context): CommandResult
    {
        $config = $context->getConfig();
        $keys = array_keys($config);
        sort($keys);

        $result = array_map(
            fn(string $key) => [
                'key' => $key,
                'type' => gettype($config[$key]),
            ],
            $keys
        );

        return CommandResult::success($result, 'Configuration keys (use "config <key>" to inspect):');
    }

    private function getConfigValue(ContextInterface $context, string $key): CommandResult
    {
        if (!$context->has($key)) {
            return CommandResult::failure(
                sprintf("Configuration key '%s' not found", $key)
            );
        }

        $value = $context->get($key);

        return CommandResult::success([
            'key' => $key,
            'value' => $value,
            'type' => gettype($value),
        ]);
    }

    public function getDescription(): string
    {
        return 'Read configuration values';
    }

    public function getUsage(): array
    {
        return [
            'config                    - List all top-level config keys',
            'config <key>              - Get value for specific key',
            'config database.default   - Dot-notation supported',
        ];
    }
}
