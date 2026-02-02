<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;

/**
 * Handler for listing HTTP routes.
 *
 * This handler expects the context to provide route information.
 * For Hyperf, this is extracted from the DispatcherFactory.
 */
final class RoutesHandler implements CommandHandlerInterface
{
    public function getCommand(): string
    {
        return 'routes';
    }

    public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
    {
        $filter = $command->getArgument(0);
        $methodFilter = $command->getOption('method');

        $routes = $this->getRoutes($context);

        if ($routes === null) {
            return CommandResult::failure('Route information not available in this context');
        }

        if ($filter !== null && $filter !== '' && is_scalar($filter)) {
            $routes = $this->filterByPath($routes, (string) $filter);
        }

        if ($methodFilter !== null && $methodFilter !== '' && is_scalar($methodFilter)) {
            $routes = $this->filterByMethod($routes, strtoupper((string) $methodFilter));
        }

        if (empty($routes)) {
            return CommandResult::success([], 'No routes match the filter criteria');
        }

        usort($routes, fn(array $a, array $b) => strcmp($a['path'], $b['path']));

        return CommandResult::success($routes, sprintf('Found %d route(s)', count($routes)));
    }

    /**
     * @return array<array{method: string, path: string, handler: string}>|null
     */
    private function getRoutes(ContextInterface $context): ?array
    {
        // Check if context provides routes directly
        $routes = $context->get('_routes');
        if (is_array($routes)) {
            return $routes;
        }

        // Try to get routes from Hyperf DispatcherFactory
        $container = $context->getContainer();
        if ($container->has('Hyperf\HttpServer\Router\DispatcherFactory')) {
            return $this->extractHyperfRoutes($container);
        }

        return null;
    }

    /**
     * @param array<array{method: string, path: string, handler: string}> $routes
     * @return array<array{method: string, path: string, handler: string}>
     */
    private function filterByPath(array $routes, string $pattern): array
    {
        return array_values(array_filter(
            $routes,
            fn(array $route) => str_contains($route['path'], $pattern)
        ));
    }

    /**
     * @param array<array{method: string, path: string, handler: string}> $routes
     * @return array<array{method: string, path: string, handler: string}>
     */
    private function filterByMethod(array $routes, string $method): array
    {
        return array_values(array_filter(
            $routes,
            fn(array $route) => $route['method'] === $method
        ));
    }

    /**
     * @return array<array{method: string, path: string, handler: string}>
     */
    private function extractHyperfRoutes(mixed $container): array
    {
        $routes = [];

        if (!is_object($container) || !method_exists($container, 'get')) {
            return $routes;
        }

        try {
            $factory = $container->get('Hyperf\HttpServer\Router\DispatcherFactory');
            if (!is_object($factory) || !method_exists($factory, 'getRouter')) {
                return $routes;
            }

            $router = $factory->getRouter('http');
            if (!is_object($router)) {
                return $routes;
            }

            $reflection = new \ReflectionProperty($router, 'routes');
            $reflection->setAccessible(true);
            $routeData = $reflection->getValue($router);

            if (!is_array($routeData)) {
                return $routes;
            }

            foreach ($routeData as $method => $paths) {
                if (!is_array($paths)) {
                    continue;
                }
                foreach ($paths as $path => $handler) {
                    $handlerStr = $this->formatHandler($handler);
                    $routes[] = [
                        'method' => is_scalar($method) ? (string) $method : '',
                        'path' => is_scalar($path) ? (string) $path : '',
                        'handler' => $handlerStr,
                    ];
                }
            }
        } catch (\Throwable) {
            // Router structure may vary; return empty on error
        }

        return $routes;
    }

    private function formatHandler(mixed $handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if (is_array($handler)) {
            if (isset($handler[0], $handler[1])) {
                $class = is_object($handler[0]) ? get_class($handler[0]) : (string) $handler[0];
                return $class . '::' . $handler[1];
            }
            return json_encode($handler) ?: '[array]';
        }

        if ($handler instanceof \Closure) {
            return 'Closure';
        }

        return gettype($handler);
    }

    public function getDescription(): string
    {
        return 'List HTTP routes registered in the application';
    }

    public function getUsage(): array
    {
        return [
            'routes                    - List all routes',
            'routes /api               - Filter routes containing "/api"',
            'routes --method=GET       - Filter by HTTP method',
            'routes /users --method=POST  - Combined filters',
        ];
    }
}
