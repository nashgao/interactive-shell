<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\AbstractCommandHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractCommandHandler::class)]
final class AbstractCommandHandlerTest extends TestCase
{
    public function testGetDescriptionReturnsEmptyStringByDefault(): void
    {
        $handler = $this->createConcreteHandler('test-cmd');

        $this->assertSame('', $handler->getDescription());
    }

    public function testGetUsageReturnsEmptyArrayByDefault(): void
    {
        $handler = $this->createConcreteHandler('test-cmd');

        $this->assertSame([], $handler->getUsage());
    }

    public function testSubclassCanOverrideDescription(): void
    {
        $handler = new class extends AbstractCommandHandler {
            public function getCommand(): string
            {
                return 'custom';
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success('ok');
            }

            public function getDescription(): string
            {
                return 'Custom description';
            }
        };

        $this->assertSame('Custom description', $handler->getDescription());
    }

    public function testSubclassCanOverrideUsage(): void
    {
        $handler = new class extends AbstractCommandHandler {
            public function getCommand(): string
            {
                return 'custom';
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success('ok');
            }

            /** @return array<string> */
            public function getUsage(): array
            {
                return ['custom --flag', 'custom <arg>'];
            }
        };

        $this->assertSame(['custom --flag', 'custom <arg>'], $handler->getUsage());
    }

    public function testImplementsCommandHandlerInterface(): void
    {
        $handler = $this->createConcreteHandler('test');

        $this->assertInstanceOf(
            \NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface::class,
            $handler
        );
    }

    public function testGetCommandReturnsDeclaredCommand(): void
    {
        $handler = $this->createConcreteHandler('my-command');

        $this->assertSame('my-command', $handler->getCommand());
    }

    private function createConcreteHandler(string $commandName): AbstractCommandHandler
    {
        return new class($commandName) extends AbstractCommandHandler {
            public function __construct(private readonly string $commandName)
            {
            }

            public function getCommand(): string
            {
                return $this->commandName;
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success('handled');
            }
        };
    }
}
