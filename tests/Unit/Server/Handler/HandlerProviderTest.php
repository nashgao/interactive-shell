<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler;

use NashGao\InteractiveShell\Command\CommandResult;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\AbstractCommandHandler;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use NashGao\InteractiveShell\Server\Handler\CommandRegistry;
use NashGao\InteractiveShell\Server\Handler\HandlerProviderInterface;
use NashGao\InteractiveShell\Server\Hyperf\HandlerDiscovery;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\AnnotatedProvider;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\EmptyProvider;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\MultiHandlerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerProviderInterface::class)]
#[CoversClass(HandlerDiscovery::class)]
#[CoversClass(CommandRegistry::class)]
final class HandlerProviderTest extends TestCase
{
    public function testProviderHandlersAreRegisteredAndCallable(): void
    {
        $registry = new CommandRegistry();
        $provider = new MultiHandlerProvider();

        $registry->registerMany($provider->getHandlers());

        $this->assertTrue($registry->has('provider-cmd-1'));
        $this->assertTrue($registry->has('provider-cmd-2'));

        $command = new ParsedCommand(
            command: 'provider-cmd-1',
            arguments: [],
            options: [],
            raw: 'provider-cmd-1',
            hasVerticalTerminator: false
        );

        $context = $this->createMock(ContextInterface::class);
        $result = $registry->execute($command, $context);

        $this->assertTrue($result->success);
        $this->assertSame('from-provider-1', $result->data);
    }

    public function testAutoDiscoveredProvidersAreFoundAndResolved(): void
    {
        $discovery = new HandlerDiscovery();

        $providers = $discovery->discoverProviders(
            [AnnotatedProvider::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            fn(string $class): HandlerProviderInterface => new $class(),
        );

        $this->assertCount(1, $providers);
        $this->assertInstanceOf(HandlerProviderInterface::class, $providers[0]);

        $handlers = iterator_to_array($providers[0]->getHandlers());
        $this->assertCount(1, $handlers);
        $this->assertSame('annotated', $handlers[0]->getCommand());
    }

    public function testEmptyProviderRegistersNothing(): void
    {
        $registry = new CommandRegistry();
        $provider = new EmptyProvider();

        $registry->registerMany($provider->getHandlers());

        $this->assertSame(0, $registry->count());
    }

    public function testIndividualHandlerOverridesProviderHandler(): void
    {
        $registry = new CommandRegistry();

        // Provider registers a handler for 'annotated'
        $provider = new AnnotatedProvider();
        $registry->registerMany($provider->getHandlers());

        // Individual handler overrides same command
        $overrideHandler = new class extends AbstractCommandHandler {
            public function getCommand(): string
            {
                return 'annotated';
            }

            public function handle(ParsedCommand $command, ContextInterface $context): CommandResult
            {
                return CommandResult::success('overridden');
            }
        };

        $registry->register($overrideHandler);

        $command = new ParsedCommand(
            command: 'annotated',
            arguments: [],
            options: [],
            raw: 'annotated',
            hasVerticalTerminator: false
        );

        $context = $this->createMock(ContextInterface::class);
        $result = $registry->execute($command, $context);

        $this->assertTrue($result->success);
        $this->assertSame('overridden', $result->data);
    }

    public function testDiscoverProvidersSkipsNonProviderClasses(): void
    {
        $discovery = new HandlerDiscovery();

        $providers = $discovery->discoverProviders(
            [
                AnnotatedProvider::class => '/fake/path.php',
                // AnnotatedHandler implements CommandHandlerInterface, not HandlerProviderInterface
                \NashGao\InteractiveShell\Tests\Fixtures\Handler\AnnotatedHandler::class => '/fake/path.php',
            ],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            fn(string $class): HandlerProviderInterface => new $class(),
        );

        $this->assertCount(1, $providers);
    }

    public function testDiscoverProvidersReturnsEmptyForEmptyPrefixes(): void
    {
        $discovery = new HandlerDiscovery();

        $providers = $discovery->discoverProviders(
            [AnnotatedProvider::class => '/fake/path.php'],
            [],
            fn(string $class): HandlerProviderInterface => new $class(),
        );

        $this->assertCount(0, $providers);
    }

    public function testDiscoverProvidersSkipsWhenResolverReturnsNull(): void
    {
        $discovery = new HandlerDiscovery();

        $providers = $discovery->discoverProviders(
            [AnnotatedProvider::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            fn(string $class): ?HandlerProviderInterface => null,
        );

        $this->assertCount(0, $providers);
    }
}
