<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Specification;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\EchoHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\ErrorHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestContext;
use NashGao\InteractiveShell\Tests\Fixtures\Server\TestServer;
use PHPUnit\Framework\TestCase;

/**
 * Server Consumer Specification Tests.
 *
 * These tests define expected behavior from the SERVER EMBEDDER's perspective.
 * A server consumer is someone who:
 * - Creates a SocketServer or uses CommandRegistry directly
 * - Registers custom command handlers
 * - Provides context for handlers to access
 *
 * SPECIFICATION-FIRST: These tests define what a server embedder expects,
 * NOT what the implementation currently does.
 *
 * Pre-Test Checklist:
 * - [x] Testing from the consumer's perspective
 * - [x] Tests would fail if the feature was broken
 * - [x] Written WITHOUT reading implementation first
 * - [x] Test names describe requirements, not implementation
 */
final class ServerConsumerSpecTest extends TestCase
{
    /**
     * SPECIFICATION: Server consumer can register custom command handlers.
     */
    public function testServerConsumerCanRegisterCustomHandlers(): void
    {
        // Given: A server consumer creates a server
        $server = new TestServer();

        // When: Consumer registers custom handlers
        $server->register(new EchoHandler());
        $server->register(new ErrorHandler());

        // Then: Handlers are available
        self::assertTrue($server->hasCommand('echo'), 'echo command should be registered');
        self::assertTrue($server->hasCommand('fail'), 'fail command should be registered');
        self::assertFalse($server->hasCommand('unknown'), 'unknown command should not exist');
    }

    /**
     * SPECIFICATION: Registered handlers can be executed through the server.
     */
    public function testServerConsumerCanExecuteRegisteredHandlers(): void
    {
        // Given: Server with registered handler
        $server = new TestServer();
        $server->register(new EchoHandler());

        // When: Consumer dispatches a command
        $command = new ParsedCommand('echo', ['hello', 'world'], [], 'echo hello world', false);
        $result = $server->dispatch($command);

        // Then: Handler is executed and returns result
        self::assertTrue($result->success);
        self::assertSame('hello world', $result->data);
    }

    /**
     * SPECIFICATION: Handlers receive the context when executed.
     */
    public function testServerConsumerHandlersReceiveContext(): void
    {
        // Given: Server with context containing config
        $context = new TestContext([
            'app.name' => 'test-app',
            'debug' => true,
        ]);
        $server = new TestServer($context);

        // Create a handler that reads from context
        $contextReader = new class implements CommandHandlerInterface {
            public function getCommand(): string
            {
                return 'read-config';
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                $appName = $context->get('app.name');
                $debug = $context->get('debug');
                return CommandResult::success([
                    'app_name' => $appName,
                    'debug' => $debug,
                ]);
            }

            public function getDescription(): string
            {
                return 'Read config from context';
            }

            /** @return array<string> */
            public function getUsage(): array
            {
                return ['read-config'];
            }
        };

        $server->register($contextReader);

        // When: Handler is executed
        $result = $server->dispatch(new ParsedCommand('read-config', [], [], 'read-config', false));

        // Then: Handler could access context
        self::assertTrue($result->success);
        self::assertIsArray($result->data);
        self::assertSame('test-app', $result->data['app_name']);
        self::assertTrue($result->data['debug']);
    }

    /**
     * SPECIFICATION: Server consumer can list all registered commands.
     */
    public function testServerConsumerCanListRegisteredCommands(): void
    {
        // Given: Server with multiple handlers
        $server = new TestServer();
        $server->register(new EchoHandler());
        $server->register(new ErrorHandler());

        // When: Consumer requests command list
        $commands = $server->getCommands();

        // Then: All commands are listed
        self::assertContains('echo', $commands);
        self::assertContains('fail', $commands);
    }

    /**
     * SPECIFICATION: Server consumer can access the underlying registry.
     */
    public function testServerConsumerCanAccessRegistry(): void
    {
        // Given: A server
        $server = new TestServer();
        $server->register(new EchoHandler());

        // When: Consumer accesses registry
        $registry = $server->getRegistry();

        // Then: Registry is accessible
        self::assertInstanceOf(CommandRegistry::class, $registry);
        self::assertTrue($registry->has('echo'));
    }

    /**
     * SPECIFICATION: Server consumer can access the context.
     */
    public function testServerConsumerCanAccessContext(): void
    {
        // Given: Server with custom context
        $context = new TestContext(['custom' => 'value']);
        $server = new TestServer($context);

        // When: Consumer accesses context
        $serverContext = $server->getContext();

        // Then: Context is accessible
        self::assertSame('value', $serverContext->get('custom'));
    }

    /**
     * SPECIFICATION: Unknown commands return a failure result.
     */
    public function testUnknownCommandsReturnFailure(): void
    {
        // Given: Server without the requested command
        $server = new TestServer();

        // When: Consumer dispatches unknown command
        $result = $server->dispatch(new ParsedCommand('unknown', [], [], 'unknown', false));

        // Then: Failure result is returned
        self::assertFalse($result->success);
        self::assertStringContainsString('Unknown command', $result->error ?? '');
    }

    /**
     * SPECIFICATION: Server consumer can register handlers with method chaining.
     */
    public function testServerConsumerCanChainHandlerRegistration(): void
    {
        // Given: A server
        $server = new TestServer();

        // When: Consumer chains registrations
        $result = $server
            ->register(new EchoHandler())
            ->register(new ErrorHandler());

        // Then: Chaining works and both handlers are registered
        self::assertSame($server, $result, 'register() should return $this');
        self::assertTrue($server->hasCommand('echo'));
        self::assertTrue($server->hasCommand('fail'));
    }

    /**
     * SPECIFICATION: Command registry can set a fallback handler for unknown commands.
     */
    public function testRegistryCanSetFallbackHandler(): void
    {
        // Given: A registry with a fallback handler
        $registry = new CommandRegistry();
        $fallback = new class implements CommandHandlerInterface {
            public function getCommand(): string
            {
                return '*'; // Fallback
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success("Fallback handled: {$command->command}");
            }

            public function getDescription(): string
            {
                return 'Fallback handler';
            }

            /** @return array<string> */
            public function getUsage(): array
            {
                return [];
            }
        };

        $registry->setFallbackHandler($fallback);

        // When: Unknown command is executed
        $context = new TestContext();
        $result = $registry->execute(
            new ParsedCommand('anything', [], [], 'anything', false),
            $context
        );

        // Then: Fallback handler is used
        self::assertTrue($result->success);
        self::assertSame('Fallback handled: anything', $result->data);
    }

    /**
     * SPECIFICATION: Handler results can be successful with data and message.
     */
    public function testHandlerCanReturnSuccessWithDataAndMessage(): void
    {
        // Given: A handler that returns rich result
        $handler = new class implements CommandHandlerInterface {
            public function getCommand(): string
            {
                return 'rich';
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success(
                    ['items' => [1, 2, 3]],
                    'Found 3 items'
                );
            }

            public function getDescription(): string
            {
                return 'Rich result handler';
            }

            /** @return array<string> */
            public function getUsage(): array
            {
                return ['rich'];
            }
        };

        $server = new TestServer();
        $server->register($handler);

        // When: Command is executed
        $result = $server->dispatch(new ParsedCommand('rich', [], [], 'rich', false));

        // Then: Both data and message are available
        self::assertTrue($result->success);
        self::assertSame(['items' => [1, 2, 3]], $result->data);
        self::assertSame('Found 3 items', $result->message);
    }

    /**
     * SPECIFICATION: Handler results can be failures with error message.
     */
    public function testHandlerCanReturnFailureWithError(): void
    {
        // Given: Error handler
        $server = new TestServer();
        $server->register(new ErrorHandler());

        // When: Failure command is executed
        $result = $server->dispatch(new ParsedCommand('fail', ['custom error'], [], 'fail custom error', false));

        // Then: Error is accessible
        self::assertFalse($result->success);
        self::assertSame('custom error', $result->error);
    }

    /**
     * SPECIFICATION: Handlers can access command arguments.
     */
    public function testHandlerCanAccessCommandArguments(): void
    {
        // Given: Echo handler that uses arguments
        $server = new TestServer();
        $server->register(new EchoHandler());

        // When: Command with arguments is executed
        $result = $server->dispatch(new ParsedCommand(
            'echo',
            ['arg1', 'arg2', 'arg3'],
            [],
            'echo arg1 arg2 arg3',
            false
        ));

        // Then: Handler received and processed arguments
        self::assertTrue($result->success);
        self::assertSame('arg1 arg2 arg3', $result->data);
    }

    /**
     * SPECIFICATION: Handlers can access command options.
     */
    public function testHandlerCanAccessCommandOptions(): void
    {
        // Given: A handler that reads options
        $handler = new class implements CommandHandlerInterface {
            public function getCommand(): string
            {
                return 'options';
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success([
                    'format' => $command->getOption('format'),
                    'verbose' => $command->hasOption('verbose'),
                ]);
            }

            public function getDescription(): string
            {
                return 'Options handler';
            }

            /** @return array<string> */
            public function getUsage(): array
            {
                return ['options --format=json --verbose'];
            }
        };

        $server = new TestServer();
        $server->register($handler);

        // When: Command with options is executed
        $result = $server->dispatch(new ParsedCommand(
            'options',
            [],
            ['format' => 'json', 'verbose' => true],
            'options --format=json --verbose',
            false
        ));

        // Then: Handler could access options
        self::assertTrue($result->success);
        self::assertIsArray($result->data);
        self::assertSame('json', $result->data['format']);
        self::assertTrue($result->data['verbose']);
    }

    /**
     * SPECIFICATION: Handlers provide description and usage for help.
     */
    public function testHandlersProvidesDescriptionAndUsage(): void
    {
        // Given: A handler
        $handler = new EchoHandler();

        // Then: Description and usage are available
        self::assertNotEmpty($handler->getDescription());
        self::assertIsArray($handler->getUsage());
    }

    /**
     * SPECIFICATION: Context provides access to container for dependency lookup.
     */
    public function testContextProvidesContainerAccess(): void
    {
        // Given: A context
        $context = new TestContext();

        // When: Consumer accesses container
        $container = $context->getContainer();

        // Then: Container is accessible (even if limited)
        self::assertInstanceOf(\Psr\Container\ContainerInterface::class, $container);
    }

    /**
     * SPECIFICATION: Context provides full config access.
     */
    public function testContextProvidesFullConfigAccess(): void
    {
        // Given: Context with config
        $context = new TestContext([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        // When: Consumer accesses full config
        $config = $context->getConfig();

        // Then: All config is returned
        self::assertSame(['key1' => 'value1', 'key2' => 'value2'], $config);
    }

    /**
     * SPECIFICATION: Context supports has() check for config keys.
     */
    public function testContextSupportsHasCheck(): void
    {
        // Given: Context with config
        $context = new TestContext(['exists' => true]);

        // Then: has() works correctly
        self::assertTrue($context->has('exists'));
        self::assertFalse($context->has('not_exists'));
    }
}
