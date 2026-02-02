<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\HyperfCommandHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(HyperfCommandHandler::class)]
final class HyperfCommandHandlerTest extends TestCase
{
    private HyperfCommandHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new HyperfCommandHandler();
    }

    public function testGetCommandReturnsStar(): void
    {
        $this->assertSame('*', $this->handler->getCommand());
    }

    public function testGetDescriptionReturnsNonEmptyString(): void
    {
        $description = $this->handler->getDescription();

        $this->assertNotEmpty($description);
        $this->assertStringContainsString('command', strtolower($description));
    }

    public function testGetUsageReturnsNonEmptyArray(): void
    {
        $usage = $this->handler->getUsage();

        $this->assertNotEmpty($usage);
        $this->assertGreaterThanOrEqual(2, count($usage));
    }

    public function testHandleExecutesCommandSuccessfully(): void
    {
        $consoleCommand = $this->createTestCommand('migrate', Command::SUCCESS, 'Migration completed');
        $app = $this->createMockApplication(['migrate' => $consoleCommand]);
        $context = $this->createMockContext($app);

        $command = $this->createCommand('migrate', [], []);
        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsString($result->data);
        $this->assertStringContainsString('Migration completed', $result->data);
    }

    public function testHandleReturnsFailureOnCommandFailure(): void
    {
        $consoleCommand = $this->createTestCommand('migrate', Command::FAILURE, 'Migration failed');
        $app = $this->createMockApplication(['migrate' => $consoleCommand]);
        $context = $this->createMockContext($app);

        $command = $this->createCommand('migrate', [], []);
        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Migration failed', $result->error ?? '');
    }

    public function testHandleReturnsFailureWhenCommandNotFound(): void
    {
        // Create mock directly to configure has() to always return false
        $app = $this->createMock(Application::class);
        $app->method('has')->willReturn(false);
        $context = $this->createMockContext($app);

        $command = $this->createCommand('nonexistent', [], []);
        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Unknown command', $result->error ?? '');
    }

    public function testHandleReturnsFailureWhenNoConsoleApp(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $context = $this->createMock(ContextInterface::class);
        $context->method('getContainer')->willReturn($container);

        $command = $this->createCommand('migrate', [], []);
        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not available', $result->error ?? '');
    }

    public function testHandleCatchesExceptions(): void
    {
        $consoleCommand = $this->createMock(Command::class);
        $consoleCommand->method('getName')->willReturn('failing');
        $consoleCommand->method('getDefinition')->willReturn(new InputDefinition());
        $consoleCommand->method('run')->willThrowException(new \RuntimeException('Command error'));

        $app = $this->createMockApplication(['failing' => $consoleCommand]);
        $context = $this->createMockContext($app);

        $command = $this->createCommand('failing', [], []);
        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Command error', $result->error ?? '');
    }

    public function testBuildInputMapsPositionalArguments(): void
    {
        $definition = new InputDefinition([
            new InputArgument('name', InputArgument::REQUIRED),
            new InputArgument('table', InputArgument::OPTIONAL),
        ]);

        $consoleCommand = $this->createMock(Command::class);
        $consoleCommand->method('getName')->willReturn('gen:model');
        $consoleCommand->method('getDefinition')->willReturn($definition);
        $consoleCommand->method('run')->willReturnCallback(
            function (InputInterface $input, OutputInterface $output) use ($definition) {
                // Bind input to definition like real Command::run() does
                $input->bind($definition);
                $output->write('name=' . $input->getArgument('name'));
                if ($input->getArgument('table')) {
                    $output->write(',table=' . $input->getArgument('table'));
                }
                return Command::SUCCESS;
            }
        );

        $app = $this->createMockApplication(['gen:model' => $consoleCommand]);
        $context = $this->createMockContext($app);

        $command = $this->createCommand('gen:model', ['User', 'users'], []);
        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsString($result->data);
        $this->assertStringContainsString('name=User', $result->data);
        $this->assertStringContainsString('table=users', $result->data);
    }

    public function testBuildInputMapsOptions(): void
    {
        $definition = new InputDefinition([
            new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force the operation'),
            new InputOption('env', 'e', InputOption::VALUE_REQUIRED, 'Environment'),
        ]);

        $consoleCommand = $this->createMock(Command::class);
        $consoleCommand->method('getName')->willReturn('migrate');
        $consoleCommand->method('getDefinition')->willReturn($definition);
        $consoleCommand->method('run')->willReturnCallback(
            function (InputInterface $input, OutputInterface $output) use ($definition) {
                // Bind input to definition like real Command::run() does
                $input->bind($definition);
                $output->write('force=' . ($input->getOption('force') ? 'yes' : 'no'));
                $output->write(',env=' . ($input->getOption('env') ?? 'none'));
                return Command::SUCCESS;
            }
        );

        $app = $this->createMockApplication(['migrate' => $consoleCommand]);
        $context = $this->createMockContext($app);

        $command = $this->createCommand('migrate', [], ['force' => true, 'env' => 'testing']);
        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsString($result->data);
        $this->assertStringContainsString('force=yes', $result->data);
        $this->assertStringContainsString('env=testing', $result->data);
    }

    public function testBuildInputMapsShortOptions(): void
    {
        $definition = new InputDefinition([
            new InputOption('verbose', 'v', InputOption::VALUE_NONE, 'Verbose output'),
        ]);

        $consoleCommand = $this->createMock(Command::class);
        $consoleCommand->method('getName')->willReturn('test');
        $consoleCommand->method('getDefinition')->willReturn($definition);
        $consoleCommand->method('run')->willReturnCallback(
            function (InputInterface $input, OutputInterface $output) use ($definition) {
                // Bind input to definition like real Command::run() does
                $input->bind($definition);
                $output->write('verbose=' . ($input->getOption('verbose') ? 'yes' : 'no'));
                return Command::SUCCESS;
            }
        );

        $app = $this->createMockApplication(['test' => $consoleCommand]);
        $context = $this->createMockContext($app);

        // Single char option should use short format
        $command = $this->createCommand('test', [], ['v' => true]);
        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsString($result->data);
        $this->assertStringContainsString('verbose=yes', $result->data);
    }

    public function testHandleReturnsGenericFailureMessageWhenOutputEmpty(): void
    {
        $consoleCommand = $this->createMock(Command::class);
        $consoleCommand->method('getName')->willReturn('fail');
        $consoleCommand->method('getDefinition')->willReturn(new InputDefinition());
        $consoleCommand->method('run')->willReturn(Command::FAILURE);

        $app = $this->createMockApplication(['fail' => $consoleCommand]);
        $context = $this->createMockContext($app);

        $command = $this->createCommand('fail', [], []);
        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('exit code', $result->error ?? '');
    }

    public function testBuildInputCollectsArrayArguments(): void
    {
        $definition = new InputDefinition([
            new InputArgument('names', InputArgument::IS_ARRAY, 'List of names'),
        ]);

        $consoleCommand = $this->createMock(Command::class);
        $consoleCommand->method('getName')->willReturn('greet');
        $consoleCommand->method('getDefinition')->willReturn($definition);
        $consoleCommand->method('run')->willReturnCallback(
            function (InputInterface $input, OutputInterface $output) use ($definition) {
                // Bind input to definition like real Command::run() does
                $input->bind($definition);
                /** @var array<string> $names */
                $names = $input->getArgument('names');
                $output->write('names=' . implode(',', $names));
                return Command::SUCCESS;
            }
        );

        $app = $this->createMockApplication(['greet' => $consoleCommand]);
        $context = $this->createMockContext($app);

        $command = $this->createCommand('greet', ['Alice', 'Bob', 'Charlie'], []);
        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsString($result->data);
        $this->assertStringContainsString('names=Alice,Bob,Charlie', $result->data);
    }

    public function testBuildInputMixedScalarAndArrayArguments(): void
    {
        $definition = new InputDefinition([
            new InputArgument('greeting', InputArgument::REQUIRED, 'The greeting'),
            new InputArgument('names', InputArgument::IS_ARRAY, 'List of names'),
        ]);

        $consoleCommand = $this->createMock(Command::class);
        $consoleCommand->method('getName')->willReturn('greet');
        $consoleCommand->method('getDefinition')->willReturn($definition);
        $consoleCommand->method('run')->willReturnCallback(
            function (InputInterface $input, OutputInterface $output) use ($definition) {
                // Bind input to definition like real Command::run() does
                $input->bind($definition);
                /** @var string $greeting */
                $greeting = $input->getArgument('greeting');
                /** @var array<string> $names */
                $names = $input->getArgument('names');
                $output->write("greeting={$greeting},names=" . implode(',', $names));
                return Command::SUCCESS;
            }
        );

        $app = $this->createMockApplication(['greet' => $consoleCommand]);
        $context = $this->createMockContext($app);

        $command = $this->createCommand('greet', ['Hello', 'Alice', 'Bob'], []);
        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsString($result->data);
        $this->assertStringContainsString('greeting=Hello', $result->data);
        $this->assertStringContainsString('names=Alice,Bob', $result->data);
    }

    private function createTestCommand(string $name, int $exitCode, string $output): Command
    {
        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn($name);
        $command->method('getDefinition')->willReturn(new InputDefinition());
        $command->method('run')->willReturnCallback(
            function (InputInterface $input, OutputInterface $out) use ($exitCode, $output) {
                $out->write($output);
                return $exitCode;
            }
        );

        return $command;
    }

    /**
     * @param array<string, Command> $commands
     * @return Application&MockObject
     */
    private function createMockApplication(array $commands): Application&MockObject
    {
        $app = $this->createMock(Application::class);
        $app->method('all')->willReturn($commands);
        $app->method('has')->willReturnCallback(fn(string $name) => isset($commands[$name]));
        $app->method('find')->willReturnCallback(fn(string $name) => $commands[$name] ?? null);

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
