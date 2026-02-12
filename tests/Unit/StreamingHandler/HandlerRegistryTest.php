<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\StreamingHandler;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\StreamingHandler\HandlerContext;
use NashGao\InteractiveShell\StreamingHandler\HandlerInterface;
use NashGao\InteractiveShell\StreamingHandler\HandlerRegistry;
use NashGao\InteractiveShell\StreamingHandler\HandlerResult;
use NashGao\InteractiveShell\Transport\StreamingTransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

#[CoversClass(HandlerRegistry::class)]
final class HandlerRegistryTest extends TestCase
{
    public function testRegisterAndHas(): void
    {
        $registry = new HandlerRegistry();
        $handler = $this->createHandler(['test', 't']);

        $registry->register($handler);

        $this->assertTrue($registry->has('test'));
        $this->assertTrue($registry->has('t'));
        $this->assertFalse($registry->has('unknown'));
    }

    public function testGetReturnsHandlerOrNull(): void
    {
        $registry = new HandlerRegistry();
        $handler = $this->createHandler(['test']);
        $registry->register($handler);

        $this->assertSame($handler, $registry->get('test'));
        $this->assertNull($registry->get('unknown'));
    }

    public function testRegisterManyRegistersAll(): void
    {
        $registry = new HandlerRegistry();
        $h1 = $this->createHandler(['cmd1']);
        $h2 = $this->createHandler(['cmd2']);

        $registry->registerMany([$h1, $h2]);

        $this->assertTrue($registry->has('cmd1'));
        $this->assertTrue($registry->has('cmd2'));
    }

    public function testExecuteDispatchesToHandler(): void
    {
        $registry = new HandlerRegistry();
        $handler = $this->createHandler(['test'], HandlerResult::success('handled'));
        $registry->register($handler);

        $command = new ParsedCommand('test', [], [], 'test', false);
        $context = $this->createStubContext();

        $result = $registry->execute($command, $context);

        $this->assertNotNull($result);
        $this->assertSame('handled', $result->message);
    }

    public function testExecuteReturnsNullForUnknownCommand(): void
    {
        $registry = new HandlerRegistry();
        $command = new ParsedCommand('unknown', [], [], 'unknown', false);
        $context = $this->createStubContext();

        $this->assertNull($registry->execute($command, $context));
    }

    public function testGetCommandListReturnsPrimaryCommands(): void
    {
        $registry = new HandlerRegistry();
        $registry->register($this->createHandler(['alpha', 'a']));
        $registry->register($this->createHandler(['beta', 'b']));

        $commands = $registry->getCommandList();
        $this->assertContains('alpha', $commands);
        $this->assertContains('beta', $commands);
        $this->assertNotContains('a', $commands);
    }

    public function testGetCommandDescriptionsIncludesAliases(): void
    {
        $registry = new HandlerRegistry();
        $registry->register($this->createHandler(['test', 't'], description: 'Test command'));

        $descriptions = $registry->getCommandDescriptions();
        $this->assertArrayHasKey('test', $descriptions);
        $this->assertStringContainsString('aliases: t', $descriptions['test']);
    }

    public function testGetUsageReturnsExamples(): void
    {
        $registry = new HandlerRegistry();
        $registry->register($this->createHandler(['test'], usage: ['test arg1', 'test --flag']));

        $usage = $registry->getUsage('test');
        $this->assertSame(['test arg1', 'test --flag'], $usage);
    }

    public function testGetUsageReturnsEmptyForUnknownCommand(): void
    {
        $registry = new HandlerRegistry();
        $this->assertSame([], $registry->getUsage('unknown'));
    }

    public function testRemoveUnregistersAllAliases(): void
    {
        $registry = new HandlerRegistry();
        $registry->register($this->createHandler(['test', 't']));

        $this->assertTrue($registry->remove('test'));
        $this->assertFalse($registry->has('test'));
        $this->assertFalse($registry->has('t'));
    }

    public function testRemoveReturnsFalseForUnknown(): void
    {
        $registry = new HandlerRegistry();
        $this->assertFalse($registry->remove('unknown'));
    }

    public function testClearRemovesAll(): void
    {
        $registry = new HandlerRegistry();
        $registry->register($this->createHandler(['a']));
        $registry->register($this->createHandler(['b']));
        $registry->clear();

        $this->assertSame(0, $registry->count());
        $this->assertFalse($registry->has('a'));
    }

    public function testCountReturnsHandlerCount(): void
    {
        $registry = new HandlerRegistry();
        $this->assertSame(0, $registry->count());

        $registry->register($this->createHandler(['a', 'b']));
        $this->assertSame(1, $registry->count());
    }

    public function testRegisterReturnsSelfForChaining(): void
    {
        $registry = new HandlerRegistry();
        $result = $registry->register($this->createHandler(['a']));
        $this->assertSame($registry, $result);
    }

    public function testGetHandlersReturnsDeduplicated(): void
    {
        $registry = new HandlerRegistry();
        $handler = $this->createHandler(['cmd1', 'cmd2']);
        $registry->register($handler);

        $handlers = $registry->getHandlers();
        $this->assertCount(1, $handlers);
    }

    /**
     * @param array<string> $commands
     * @param array<string> $usage
     */
    private function createHandler(
        array $commands,
        ?HandlerResult $result = null,
        string $description = 'Test handler',
        array $usage = [],
    ): HandlerInterface {
        $result ??= HandlerResult::success();

        return new class ($commands, $result, $description, $usage) implements HandlerInterface {
            /**
             * @param array<string> $commands
             * @param array<string> $usage
             */
            public function __construct(
                private readonly array $commands,
                private readonly HandlerResult $result,
                private readonly string $description,
                private readonly array $usage,
            ) {}

            public function getCommands(): array
            {
                return $this->commands;
            }

            public function handle(ParsedCommand $command, HandlerContext $context): HandlerResult
            {
                return $this->result;
            }

            public function getDescription(): string
            {
                return $this->description;
            }

            public function getUsage(): array
            {
                return $this->usage;
            }
        };
    }

    private function createStubContext(): HandlerContext
    {
        $transport = $this->createStub(StreamingTransportInterface::class);

        return new readonly class (new NullOutput(), $transport) extends HandlerContext {};
    }
}
