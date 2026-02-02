<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\CommandListHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

#[CoversClass(CommandListHandler::class)]
final class CommandListHandlerTest extends TestCase
{
    private CommandListHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new CommandListHandler();
    }

    public function testGetCommandReturnsCommands(): void
    {
        $this->assertSame('commands', $this->handler->getCommand());
    }

    public function testHandleListsAllCommands(): void
    {
        $app = $this->createMockApplication([
            'migrate' => $this->createMockCommand('migrate', 'Run database migrations'),
            'cache:clear' => $this->createMockCommand('cache:clear', 'Clear the cache'),
        ]);

        $context = $this->createMockContext($app);
        $command = $this->createCommand('commands', [], []);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);
        $this->assertCount(2, $result->data);
    }

    public function testHandleFiltersCommandsByArgument(): void
    {
        $app = $this->createMockApplication([
            'migrate' => $this->createMockCommand('migrate', 'Run database migrations'),
            'migrate:rollback' => $this->createMockCommand('migrate:rollback', 'Rollback migrations'),
            'cache:clear' => $this->createMockCommand('cache:clear', 'Clear the cache'),
        ]);

        $context = $this->createMockContext($app);
        $command = $this->createCommand('commands', ['migrate'], []);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertCount(2, $result->data);
        foreach ($result->data as $cmd) {
            $this->assertStringContainsString('migrate', $cmd['name']);
        }
    }

    public function testHandleExcludesHiddenCommands(): void
    {
        $hiddenCommand = $this->createMockCommand('hidden', 'Hidden command', true);
        $app = $this->createMockApplication([
            'visible' => $this->createMockCommand('visible', 'Visible command'),
            'hidden' => $hiddenCommand,
        ]);

        $context = $this->createMockContext($app);
        $command = $this->createCommand('commands', [], []);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data);
        $this->assertSame('visible', $result->data[0]['name']);
    }

    public function testHandleReturnsSortedAlphabetically(): void
    {
        $app = $this->createMockApplication([
            'zebra' => $this->createMockCommand('zebra', 'Zebra command'),
            'alpha' => $this->createMockCommand('alpha', 'Alpha command'),
            'middle' => $this->createMockCommand('middle', 'Middle command'),
        ]);

        $context = $this->createMockContext($app);
        $command = $this->createCommand('commands', [], []);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertSame('alpha', $result->data[0]['name']);
        $this->assertSame('middle', $result->data[1]['name']);
        $this->assertSame('zebra', $result->data[2]['name']);
    }

    public function testHandleReturnsFailureWhenConsoleNotAvailable(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $context = $this->createMock(ContextInterface::class);
        $context->method('getContainer')->willReturn($container);

        $command = $this->createCommand('commands', [], []);

        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not available', $result->error ?? '');
    }

    public function testHandleReturnsSuccessWithEmptyWhenNoMatches(): void
    {
        $app = $this->createMockApplication([
            'migrate' => $this->createMockCommand('migrate', 'Run migrations'),
        ]);

        $context = $this->createMockContext($app);
        $command = $this->createCommand('commands', ['nonexistent'], []);

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);
        $this->assertEmpty($result->data);
    }

    public function testGetDescriptionReturnsNonEmptyString(): void
    {
        $description = $this->handler->getDescription();

        $this->assertNotEmpty($description);
        $this->assertStringContainsString('command', strtolower($description));
    }

    public function testGetUsageReturnsExamples(): void
    {
        $usage = $this->handler->getUsage();

        $this->assertNotEmpty($usage);
        $this->assertGreaterThan(1, count($usage));
    }

    private function createMockCommand(string $name, string $description, bool $hidden = false): Command
    {
        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn($name);
        $command->method('getDescription')->willReturn($description);
        $command->method('isHidden')->willReturn($hidden);

        return $command;
    }

    /**
     * @param array<string, Command> $commands
     */
    private function createMockApplication(array $commands): Application
    {
        $app = $this->createMock(Application::class);
        $app->method('all')->willReturn($commands);

        return $app;
    }

    private function createMockContext(Application $app): ContextInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(fn(string $id) => $id === Application::class);
        $container->method('get')
            ->willReturnCallback(fn(string $id) => $id === Application::class ? $app : null);

        $context = $this->createMock(ContextInterface::class);
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
