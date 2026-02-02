<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use Psr\Container\ContainerInterface;

/**
 * Handler for inspecting DI container bindings.
 */
final class ContainerHandler implements CommandHandlerInterface
{
    public function getCommand(): string
    {
        return 'container';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $subcommand = $command->getArgument(0);
        $container = $context->getContainer();

        $subcommandStr = is_scalar($subcommand) ? (string) $subcommand : null;

        return match ($subcommandStr) {
            'has' => $this->checkBinding($container, $command->getArgument(1)),
            'get' => $this->inspectBinding($container, $command->getArgument(1)),
            'list' => $this->listBindings($container, $command->getArgument(1)),
            null, '', 'info' => $this->showInfo($container),
            default => CommandResult::failure(
                sprintf("Unknown subcommand: '%s'", $subcommandStr),
                ['available' => ['info', 'has', 'get', 'list']]
            ),
        };
    }

    private function showInfo(ContainerInterface $container): CommandResult
    {
        $info = [
            'type' => get_class($container),
        ];

        // Try to get additional info from Hyperf container
        if (method_exists($container, 'getDefinitionSource')) {
            /** @var mixed $source */
            $source = $container->getDefinitionSource();
            if (is_object($source) && method_exists($source, 'getDefinitions')) {
                /** @var mixed $definitions */
                $definitions = $source->getDefinitions();
                if (is_array($definitions) || $definitions instanceof \Countable) {
                    $info['definitions_count'] = count($definitions);
                }
            }
        }

        return CommandResult::success($info, 'Container information');
    }

    private function checkBinding(ContainerInterface $container, mixed $id): CommandResult
    {
        if ($id === null || !is_scalar($id)) {
            return CommandResult::failure('Usage: container has <service-id>');
        }

        $idStr = (string) $id;
        $exists = $container->has($idStr);

        return CommandResult::success([
            'id' => $idStr,
            'exists' => $exists,
        ]);
    }

    private function inspectBinding(ContainerInterface $container, mixed $id): CommandResult
    {
        if ($id === null || !is_scalar($id)) {
            return CommandResult::failure('Usage: container get <service-id>');
        }

        $idStr = (string) $id;

        if (!$container->has($idStr)) {
            return CommandResult::failure(sprintf("Service '%s' not found in container", $idStr));
        }

        try {
            $instance = $container->get($idStr);
            $info = $this->describeInstance($instance);
            $info['id'] = $idStr;

            return CommandResult::success($info);
        } catch (\Throwable $e) {
            return CommandResult::failure(
                sprintf("Failed to resolve '%s': %s", $idStr, $e->getMessage())
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function describeInstance(mixed $instance): array
    {
        if (!is_object($instance)) {
            return [
                'type' => gettype($instance),
                'value' => $instance,
            ];
        }

        $class = get_class($instance);
        $info = [
            'class' => $class,
            'interfaces' => class_implements($class) ?: [],
            'traits' => class_uses($class) ?: [],
        ];

        // Add parent classes
        $parents = class_parents($class);
        if ($parents !== false && !empty($parents)) {
            $info['parents'] = array_values($parents);
        }

        return $info;
    }

    private function listBindings(ContainerInterface $container, mixed $filter): CommandResult
    {
        $bindings = $this->extractBindings($container);

        if ($bindings === null) {
            return CommandResult::failure(
                'Cannot list bindings for this container type. Use "container has <id>" to check specific services.'
            );
        }

        if ($filter !== null && is_scalar($filter)) {
            $filterStr = (string) $filter;
            $bindings = array_values(array_filter(
                $bindings,
                fn(array $b) => str_contains($b['id'], $filterStr)
            ));
        }

        usort($bindings, fn(array $a, array $b) => strcmp($a['id'], $b['id']));

        return CommandResult::success(
            $bindings,
            sprintf('Found %d binding(s)', count($bindings))
        );
    }

    /**
     * @return array<array{id: string, type: string}>|null
     */
    private function extractBindings(ContainerInterface $container): ?array
    {
        // Try Hyperf container
        if (method_exists($container, 'getDefinitionSource')) {
            /** @var mixed $source */
            $source = $container->getDefinitionSource();
            if (is_object($source) && method_exists($source, 'getDefinitions')) {
                /** @var mixed $definitions */
                $definitions = $source->getDefinitions();
                if (!is_array($definitions)) {
                    return null;
                }

                $result = [];
                foreach ($definitions as $id => $def) {
                    $result[] = [
                        'id' => is_scalar($id) ? (string) $id : '',
                        'type' => is_object($def) ? get_class($def) : gettype($def),
                    ];
                }
                return $result;
            }
        }

        return null;
    }

    public function getDescription(): string
    {
        return 'Inspect dependency injection container bindings';
    }

    public function getUsage(): array
    {
        return [
            'container              - Show container info',
            'container info         - Show container info',
            'container has <id>     - Check if service exists',
            'container get <id>     - Inspect a service',
            'container list         - List all bindings',
            'container list Logger  - Filter bindings by name',
        ];
    }
}
