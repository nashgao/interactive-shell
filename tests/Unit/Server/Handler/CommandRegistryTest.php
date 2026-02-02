<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(CommandRegistry::class)]
final class CommandRegistryTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
    }

    public function testRegisterAddsHandler(): void
    {
        $handler = $this->createHandler('test', 'Test command');

        $this->registry->register($handler);

        $this->assertTrue($this->registry->has('test'));
        $this->assertSame($handler, $this->registry->get('test'));
    }

    public function testRegisterManyAddsMultipleHandlers(): void
    {
        $handler1 = $this->createHandler('cmd1', 'First command');
        $handler2 = $this->createHandler('cmd2', 'Second command');

        $this->registry->registerMany([$handler1, $handler2]);

        $this->assertTrue($this->registry->has('cmd1'));
        $this->assertTrue($this->registry->has('cmd2'));
    }

    public function testGetReturnsNullForUnknownCommand(): void
    {
        $this->assertNull($this->registry->get('unknown'));
    }

    public function testHasReturnsFalseForUnknownCommand(): void
    {
        $this->assertFalse($this->registry->has('unknown'));
    }

    public function testExecuteRoutesToCorrectHandler(): void
    {
        $handler = $this->createHandler('test', 'Test command');
        $this->registry->register($handler);

        $command = new ParsedCommand(
            command: 'test',
            arguments: ['arg1'],
            options: ['opt' => 'value'],
            raw: 'test arg1 --opt=value',
            hasVerticalTerminator: false
        );

        $context = $this->createMock(ContextInterface::class);
        $result = $this->registry->execute($command, $context);

        $this->assertTrue($result->success);
        $this->assertSame('handled', $result->data);
    }

    public function testExecuteReturnsFailureForUnknownCommand(): void
    {
        $command = new ParsedCommand(
            command: 'unknown',
            arguments: [],
            options: [],
            raw: 'unknown',
            hasVerticalTerminator: false
        );

        $context = $this->createMock(ContextInterface::class);
        $result = $this->registry->execute($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown command', $result->error ?? '');
    }

    public function testGetCommandListReturnsAllCommandNames(): void
    {
        $this->registry->register($this->createHandler('alpha', 'Alpha'));
        $this->registry->register($this->createHandler('beta', 'Beta'));

        $list = $this->registry->getCommandList();

        $this->assertContains('alpha', $list);
        $this->assertContains('beta', $list);
        $this->assertCount(2, $list);
    }

    public function testGetCommandDescriptionsReturnsSortedDescriptions(): void
    {
        $this->registry->register($this->createHandler('zebra', 'Zebra command'));
        $this->registry->register($this->createHandler('alpha', 'Alpha command'));

        $descriptions = $this->registry->getCommandDescriptions();

        $this->assertSame(['alpha', 'zebra'], array_keys($descriptions));
        $this->assertSame('Alpha command', $descriptions['alpha']);
        $this->assertSame('Zebra command', $descriptions['zebra']);
    }

    public function testRemoveRemovesHandler(): void
    {
        $this->registry->register($this->createHandler('test', 'Test'));

        $removed = $this->registry->remove('test');

        $this->assertTrue($removed);
        $this->assertFalse($this->registry->has('test'));
    }

    public function testRemoveReturnsFalseForUnknownCommand(): void
    {
        $removed = $this->registry->remove('unknown');

        $this->assertFalse($removed);
    }

    public function testClearRemovesAllHandlers(): void
    {
        $this->registry->register($this->createHandler('cmd1', 'First'));
        $this->registry->register($this->createHandler('cmd2', 'Second'));

        $this->registry->clear();

        $this->assertSame(0, $this->registry->count());
        $this->assertEmpty($this->registry->getCommandList());
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $this->assertSame(0, $this->registry->count());

        $this->registry->register($this->createHandler('cmd1', 'First'));
        $this->assertSame(1, $this->registry->count());

        $this->registry->register($this->createHandler('cmd2', 'Second'));
        $this->assertSame(2, $this->registry->count());
    }

    public function testGetHandlersReturnsAllHandlers(): void
    {
        $handler1 = $this->createHandler('cmd1', 'First');
        $handler2 = $this->createHandler('cmd2', 'Second');

        $this->registry->register($handler1);
        $this->registry->register($handler2);

        $handlers = $this->registry->getHandlers();

        $this->assertSame($handler1, $handlers['cmd1']);
        $this->assertSame($handler2, $handlers['cmd2']);
    }

    public function testRegisterReturnsSelfForChaining(): void
    {
        $result = $this->registry->register($this->createHandler('test', 'Test'));

        $this->assertSame($this->registry, $result);
    }

    public function testRegisterManyReturnsSelfForChaining(): void
    {
        $result = $this->registry->registerMany([
            $this->createHandler('test', 'Test'),
        ]);

        $this->assertSame($this->registry, $result);
    }

    public function testSetFallbackHandlerReturnsSelfForChaining(): void
    {
        $handler = $this->createHandler('*', 'Fallback');

        $result = $this->registry->setFallbackHandler($handler);

        $this->assertSame($this->registry, $result);
    }

    public function testGetFallbackHandlerReturnsNullInitially(): void
    {
        $this->assertNull($this->registry->getFallbackHandler());
    }

    public function testGetFallbackHandlerReturnsSetHandler(): void
    {
        $handler = $this->createHandler('*', 'Fallback');

        $this->registry->setFallbackHandler($handler);

        $this->assertSame($handler, $this->registry->getFallbackHandler());
    }

    public function testExecuteUsesFallbackWhenNoMatch(): void
    {
        $fallbackHandler = $this->createHandler('*', 'Fallback', 'fallback-handled');
        $this->registry->setFallbackHandler($fallbackHandler);

        $command = new ParsedCommand(
            command: 'unknown',
            arguments: [],
            options: [],
            raw: 'unknown',
            hasVerticalTerminator: false
        );

        $context = $this->createMock(ContextInterface::class);
        $result = $this->registry->execute($command, $context);

        $this->assertTrue($result->success);
        $this->assertSame('fallback-handled', $result->data);
    }

    public function testExecutePrefersExactMatchOverFallback(): void
    {
        $exactHandler = $this->createHandler('test', 'Test', 'exact-handled');
        $fallbackHandler = $this->createHandler('*', 'Fallback', 'fallback-handled');

        $this->registry->register($exactHandler);
        $this->registry->setFallbackHandler($fallbackHandler);

        $command = new ParsedCommand(
            command: 'test',
            arguments: [],
            options: [],
            raw: 'test',
            hasVerticalTerminator: false
        );

        $context = $this->createMock(ContextInterface::class);
        $result = $this->registry->execute($command, $context);

        $this->assertTrue($result->success);
        $this->assertSame('exact-handled', $result->data);
    }

    public function testExecuteReturnsFailureWhenNoMatchAndNoFallback(): void
    {
        // No handlers, no fallback
        $command = new ParsedCommand(
            command: 'nonexistent',
            arguments: [],
            options: [],
            raw: 'nonexistent',
            hasVerticalTerminator: false
        );

        $context = $this->createMock(ContextInterface::class);
        $result = $this->registry->execute($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown command', $result->error ?? '');
    }

    private function createHandler(string $command, string $description, string $handledData = 'handled'): CommandHandlerInterface
    {
        return new class($command, $description, $handledData) implements CommandHandlerInterface {
            public function __construct(
                private readonly string $cmd,
                private readonly string $desc,
                private readonly string $handledData
            ) {}

            public function getCommand(): string
            {
                return $this->cmd;
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success($this->handledData);
            }

            public function getDescription(): string
            {
                return $this->desc;
            }

            public function getUsage(): array
            {
                return [$this->cmd];
            }
        };
    }
}
