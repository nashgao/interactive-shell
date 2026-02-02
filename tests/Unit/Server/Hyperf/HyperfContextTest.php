<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Hyperf;

use NashGao\InteractiveShell\Server\Hyperf\HyperfContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Unit tests for HyperfContext.
 *
 * Uses stub interfaces from tests/Stubs/Hyperf/ when Hyperf is not installed.
 */
#[CoversClass(HyperfContext::class)]
final class HyperfContextTest extends TestCase
{
    public function testConstructorFetchesConfigFromContainer(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->once())
            ->method('get')
            ->with($configClass)
            ->willReturn($config);

        new HyperfContext($container);
    }

    public function testGetContainerReturnsInjectedContainer(): void
    {
        [$context, $container] = $this->createContext();

        $this->assertSame($container, $context->getContainer());
    }

    public function testGetConfigReturnsTopLevelKeys(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $config->method('get')
            ->willReturnCallback(function (string $key) {
                return match ($key) {
                    'app_name' => 'TestApp',
                    'databases' => ['default' => 'mysql'],
                    default => null,
                };
            });

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($config);

        $context = new HyperfContext($container);
        $result = $context->getConfig();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('app_name', $result);
        $this->assertSame('TestApp', $result['app_name']);
    }

    public function testGetConfigExcludesNullValues(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $config->method('get')
            ->willReturnCallback(function (string $key) {
                return match ($key) {
                    'app_name' => 'TestApp',
                    'redis' => null,
                    default => null,
                };
            });

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($config);

        $context = new HyperfContext($container);
        $result = $context->getConfig();

        $this->assertArrayNotHasKey('redis', $result);
    }

    public function testGetReturnsValueForExistingKey(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $config->expects($this->once())
            ->method('get')
            ->with('database.host', null)
            ->willReturn('localhost');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($config);

        $context = new HyperfContext($container);

        $this->assertSame('localhost', $context->get('database.host'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $config->expects($this->once())
            ->method('get')
            ->with('nonexistent.key', 'default_value')
            ->willReturn('default_value');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($config);

        $context = new HyperfContext($container);

        $this->assertSame('default_value', $context->get('nonexistent.key', 'default_value'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $config->expects($this->once())
            ->method('has')
            ->with('app_name')
            ->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($config);

        $context = new HyperfContext($container);

        $this->assertTrue($context->has('app_name'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $config->expects($this->once())
            ->method('has')
            ->with('nonexistent.key')
            ->willReturn(false);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($config);

        $context = new HyperfContext($container);

        $this->assertFalse($context->has('nonexistent.key'));
    }

    /**
     * @return array{HyperfContext, ContainerInterface}
     */
    private function createContext(): array
    {
        $configClass = 'Hyperf\Contract\ConfigInterface';
        $config = $this->createMock($configClass);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($config);

        return [new HyperfContext($container), $container];
    }
}
