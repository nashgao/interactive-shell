<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\ContainerHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(ContainerHandler::class)]
final class ContainerHandlerTest extends TestCase
{
    private ContainerHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ContainerHandler();
    }

    public function testGetCommandReturnsContainer(): void
    {
        $this->assertSame('container', $this->handler->getCommand());
    }

    public function testHandleWithoutArgumentsShowsInfo(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $context = $this->createMockContext($container);

        $command = new ParsedCommand(
            command: 'container',
            arguments: [],
            options: [],
            raw: 'container',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);
        $this->assertArrayHasKey('type', $result->data);
    }

    public function testHandleInfoSubcommandShowsInfo(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $context = $this->createMockContext($container);

        $command = new ParsedCommand(
            command: 'container',
            arguments: ['info'],
            options: [],
            raw: 'container info',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('type', $result->data);
    }

    public function testHandleHasSubcommandChecksBinding(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('SomeService')->willReturn(true);

        $context = $this->createMockContext($container);

        $command = new ParsedCommand(
            command: 'container',
            arguments: ['has', 'SomeService'],
            options: [],
            raw: 'container has SomeService',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertSame('SomeService', $result->data['id']);
        $this->assertTrue($result->data['exists']);
    }

    public function testHandleHasSubcommandRequiresServiceId(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $context = $this->createMockContext($container);

        $command = new ParsedCommand(
            command: 'container',
            arguments: ['has'],
            options: [],
            raw: 'container has',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage', $result->error ?? '');
    }

    public function testHandleGetSubcommandInspectsBinding(): void
    {
        $service = new \stdClass();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('SomeService')->willReturn(true);
        $container->method('get')->with('SomeService')->willReturn($service);

        $context = $this->createMockContext($container);

        $command = new ParsedCommand(
            command: 'container',
            arguments: ['get', 'SomeService'],
            options: [],
            raw: 'container get SomeService',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertSame('SomeService', $result->data['id']);
        $this->assertSame('stdClass', $result->data['class']);
    }

    public function testHandleGetSubcommandRequiresServiceId(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $context = $this->createMockContext($container);

        $command = new ParsedCommand(
            command: 'container',
            arguments: ['get'],
            options: [],
            raw: 'container get',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Usage', $result->error ?? '');
    }

    public function testHandleGetSubcommandFailsForUnknownService(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('Unknown')->willReturn(false);

        $context = $this->createMockContext($container);

        $command = new ParsedCommand(
            command: 'container',
            arguments: ['get', 'Unknown'],
            options: [],
            raw: 'container get Unknown',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error ?? '');
    }

    public function testHandleUnknownSubcommandReturnsFailure(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $context = $this->createMockContext($container);

        $command = new ParsedCommand(
            command: 'container',
            arguments: ['invalid'],
            options: [],
            raw: 'container invalid',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown subcommand', $result->error ?? '');
    }

    public function testGetDescriptionReturnsNonEmptyString(): void
    {
        $description = $this->handler->getDescription();

        $this->assertNotEmpty($description);
    }

    public function testGetUsageReturnsExamples(): void
    {
        $usage = $this->handler->getUsage();

        $this->assertNotEmpty($usage);
        $this->assertGreaterThan(3, count($usage));
    }

    private function createMockContext(ContainerInterface $container): ContextInterface
    {
        $context = $this->createMock(ContextInterface::class);
        $context->method('getContainer')->willReturn($container);
        return $context;
    }
}
