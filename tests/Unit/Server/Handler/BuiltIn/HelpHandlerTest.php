<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\HelpHandler;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\PingHandler;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HelpHandler::class)]
final class HelpHandlerTest extends TestCase
{
    private CommandRegistry $registry;
    private HelpHandler $handler;
    private ContextInterface $context;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $this->registry->register(new PingHandler());
        $this->handler = new HelpHandler($this->registry);
        $this->registry->register($this->handler);
        $this->context = $this->createMock(ContextInterface::class);
    }

    public function testGetCommandReturnsHelp(): void
    {
        $this->assertSame('help', $this->handler->getCommand());
    }

    public function testHandleWithoutArgumentsListsAllCommands(): void
    {
        $command = new ParsedCommand(
            command: 'help',
            arguments: [],
            options: [],
            raw: 'help',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $this->context);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);

        $commands = array_column($result->data, 'command');
        $this->assertContains('ping', $commands);
        $this->assertContains('help', $commands);
    }

    public function testHandleWithCommandArgumentShowsSpecificHelp(): void
    {
        $command = new ParsedCommand(
            command: 'help',
            arguments: ['ping'],
            options: [],
            raw: 'help ping',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $this->context);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);
        $this->assertSame('ping', $result->data['command']);
        $this->assertArrayHasKey('description', $result->data);
        $this->assertArrayHasKey('usage', $result->data);
    }

    public function testHandleWithUnknownCommandReturnsFailure(): void
    {
        $command = new ParsedCommand(
            command: 'help',
            arguments: ['nonexistent'],
            options: [],
            raw: 'help nonexistent',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $this->context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown command', $result->error ?? '');
    }

    public function testCommandListIsSortedAlphabetically(): void
    {
        // Add more handlers
        $this->registry->register($this->createHandler('zebra', 'Zebra command'));
        $this->registry->register($this->createHandler('alpha', 'Alpha command'));

        $command = new ParsedCommand(
            command: 'help',
            arguments: [],
            options: [],
            raw: 'help',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $this->context);

        $commands = array_column($result->data, 'command');
        $sortedCommands = $commands;
        sort($sortedCommands);

        $this->assertSame($sortedCommands, $commands);
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
        $this->assertGreaterThanOrEqual(2, count($usage));
    }

    private function createHandler(string $command, string $description): CommandHandlerInterface
    {
        return new class($command, $description) implements CommandHandlerInterface {
            public function __construct(
                private readonly string $cmd,
                private readonly string $desc
            ) {}

            public function getCommand(): string
            {
                return $this->cmd;
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success('handled');
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
