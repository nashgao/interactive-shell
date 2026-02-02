<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\RoutesHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(RoutesHandler::class)]
final class RoutesHandlerTest extends TestCase
{
    private RoutesHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new RoutesHandler();
    }

    public function testGetCommandReturnsRoutes(): void
    {
        $this->assertSame('routes', $this->handler->getCommand());
    }

    public function testHandleListsAllRoutesWithoutFilter(): void
    {
        $routes = [
            ['method' => 'GET', 'path' => '/api/users', 'handler' => 'UserController::index'],
            ['method' => 'POST', 'path' => '/api/users', 'handler' => 'UserController::store'],
        ];

        $context = $this->createMockContext($routes);
        $command = $this->createCommand('routes', [], []);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);
        $this->assertCount(2, $result->data);
    }

    public function testHandleFiltersRoutesByPathArgument(): void
    {
        $routes = [
            ['method' => 'GET', 'path' => '/api/users', 'handler' => 'UserController::index'],
            ['method' => 'GET', 'path' => '/api/orders', 'handler' => 'OrderController::index'],
            ['method' => 'GET', 'path' => '/health', 'handler' => 'HealthController::check'],
        ];

        $context = $this->createMockContext($routes);
        $command = $this->createCommand('routes', ['/api'], []);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertCount(2, $result->data);
        foreach ($result->data as $route) {
            $this->assertStringContainsString('/api', $route['path']);
        }
    }

    public function testHandleFiltersRoutesByMethodOption(): void
    {
        $routes = [
            ['method' => 'GET', 'path' => '/api/users', 'handler' => 'UserController::index'],
            ['method' => 'POST', 'path' => '/api/users', 'handler' => 'UserController::store'],
            ['method' => 'DELETE', 'path' => '/api/users/{id}', 'handler' => 'UserController::destroy'],
        ];

        $context = $this->createMockContext($routes);
        $command = $this->createCommand('routes', [], ['method' => 'GET']);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data);
        $this->assertSame('GET', $result->data[0]['method']);
    }

    public function testHandleMethodFilterIsCaseInsensitive(): void
    {
        $routes = [
            ['method' => 'GET', 'path' => '/api/users', 'handler' => 'UserController::index'],
            ['method' => 'POST', 'path' => '/api/users', 'handler' => 'UserController::store'],
        ];

        $context = $this->createMockContext($routes);
        $command = $this->createCommand('routes', [], ['method' => 'get']);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data);
        $this->assertSame('GET', $result->data[0]['method']);
    }

    public function testHandleCombinesPathAndMethodFilters(): void
    {
        $routes = [
            ['method' => 'GET', 'path' => '/api/users', 'handler' => 'UserController::index'],
            ['method' => 'POST', 'path' => '/api/users', 'handler' => 'UserController::store'],
            ['method' => 'GET', 'path' => '/api/orders', 'handler' => 'OrderController::index'],
        ];

        $context = $this->createMockContext($routes);
        $command = $this->createCommand('routes', ['/api/users'], ['method' => 'POST']);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data);
        $this->assertSame('POST', $result->data[0]['method']);
        $this->assertSame('/api/users', $result->data[0]['path']);
    }

    public function testHandleReturnsRoutesSortedByPath(): void
    {
        $routes = [
            ['method' => 'GET', 'path' => '/z-last', 'handler' => 'ZController::index'],
            ['method' => 'GET', 'path' => '/a-first', 'handler' => 'AController::index'],
            ['method' => 'GET', 'path' => '/m-middle', 'handler' => 'MController::index'],
        ];

        $context = $this->createMockContext($routes);
        $command = $this->createCommand('routes', [], []);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertSame('/a-first', $result->data[0]['path']);
        $this->assertSame('/m-middle', $result->data[1]['path']);
        $this->assertSame('/z-last', $result->data[2]['path']);
    }

    public function testHandleReturnsFailureWhenNoRoutesAvailable(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $context = $this->createMock(ContextInterface::class);
        $context->method('get')->willReturn(null);
        $context->method('getContainer')->willReturn($container);

        $command = $this->createCommand('routes', [], []);

        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not available', $result->error ?? '');
    }

    public function testHandleReturnsEmptyWhenNoMatches(): void
    {
        $routes = [
            ['method' => 'GET', 'path' => '/api/users', 'handler' => 'UserController::index'],
        ];

        $context = $this->createMockContext($routes);
        $command = $this->createCommand('routes', ['/nonexistent'], []);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);
        $this->assertEmpty($result->data);
    }

    public function testGetDescriptionReturnsNonEmptyString(): void
    {
        $description = $this->handler->getDescription();

        $this->assertNotEmpty($description);
        $this->assertStringContainsString('route', strtolower($description));
    }

    public function testGetUsageReturnsExamples(): void
    {
        $usage = $this->handler->getUsage();

        $this->assertNotEmpty($usage);
        $this->assertGreaterThan(2, count($usage));
    }

    /**
     * @param array<array{method: string, path: string, handler: string}> $routes
     */
    private function createMockContext(array $routes): ContextInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $context = $this->createMock(ContextInterface::class);
        $context->method('get')
            ->willReturnCallback(fn(string $key) => $key === '_routes' ? $routes : null);
        $context->method('getContainer')->willReturn($container);

        return $context;
    }

    /**
     * @param array<int, string> $arguments
     * @param array<string, mixed> $options
     */
    private function createCommand(string $command, array $arguments, array $options): ParsedCommand
    {
        return new ParsedCommand(
            command: $command,
            arguments: $arguments,
            options: $options,
            raw: $command,
            hasVerticalTerminator: false
        );
    }
}
