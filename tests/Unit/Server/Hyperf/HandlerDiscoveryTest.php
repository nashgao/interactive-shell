<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Hyperf;

use NashGao\InteractiveShell\Server\Handler\AbstractCommandHandler;
use NashGao\InteractiveShell\Server\Handler\CommandHandlerInterface;
use NashGao\InteractiveShell\Server\Hyperf\HandlerDiscovery;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\AnnotatedHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\AnnotatedOverrideHandler;
use NashGao\InteractiveShell\Tests\Fixtures\Handler\UnannotatedHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerDiscovery::class)]
final class HandlerDiscoveryTest extends TestCase
{
    private HandlerDiscovery $discovery;

    protected function setUp(): void
    {
        $this->discovery = new HandlerDiscovery();
    }

    public function testDiscoversAnnotatedHandler(): void
    {
        $handlers = $this->discovery->discover(
            [AnnotatedHandler::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        $this->assertCount(1, $handlers);
        $this->assertSame('annotated', $handlers[0]->getCommand());
    }

    public function testSkipsUnannotatedHandler(): void
    {
        $handlers = $this->discovery->discover(
            [UnannotatedHandler::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        $this->assertCount(0, $handlers);
    }

    public function testSkipsAbstractClasses(): void
    {
        $handlers = $this->discovery->discover(
            [AbstractCommandHandler::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Server\\Handler\\'],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        $this->assertCount(0, $handlers);
    }

    public function testSkipsInterfaces(): void
    {
        $handlers = $this->discovery->discover(
            [CommandHandlerInterface::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Server\\Handler\\'],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        $this->assertCount(0, $handlers);
    }

    public function testAppliesCommandOverrideFromAttribute(): void
    {
        $handlers = $this->discovery->discover(
            [AnnotatedOverrideHandler::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        $this->assertCount(1, $handlers);
        $this->assertSame('custom-cmd', $handlers[0]->getCommand());
    }

    public function testAppliesDescriptionOverrideFromAttribute(): void
    {
        $handlers = $this->discovery->discover(
            [AnnotatedOverrideHandler::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        $this->assertCount(1, $handlers);
        $this->assertSame('Custom description', $handlers[0]->getDescription());
    }

    public function testReturnsEmptyWhenNoPrefixesMatch(): void
    {
        $handlers = $this->discovery->discover(
            [AnnotatedHandler::class => '/fake/path.php'],
            ['App\\NonExistent\\'],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        $this->assertCount(0, $handlers);
    }

    public function testReturnsEmptyForEmptyClassmap(): void
    {
        $handlers = $this->discovery->discover(
            [],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        $this->assertCount(0, $handlers);
    }

    public function testReturnsEmptyForEmptyPrefixes(): void
    {
        $handlers = $this->discovery->discover(
            [AnnotatedHandler::class => '/fake/path.php'],
            [],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        $this->assertCount(0, $handlers);
    }

    public function testUsesResolverCallableToInstantiateHandlers(): void
    {
        $resolverCalled = false;

        $handlers = $this->discovery->discover(
            [AnnotatedHandler::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            function (string $class) use (&$resolverCalled): CommandHandlerInterface {
                $resolverCalled = true;
                return new $class();
            },
        );

        $this->assertTrue($resolverCalled);
        $this->assertCount(1, $handlers);
    }

    public function testSkipsWhenResolverReturnsNull(): void
    {
        $handlers = $this->discovery->discover(
            [AnnotatedHandler::class => '/fake/path.php'],
            ['NashGao\\InteractiveShell\\Tests\\Fixtures\\Handler\\'],
            fn(string $class): ?CommandHandlerInterface => null,
        );

        $this->assertCount(0, $handlers);
    }

    public function testDiscoversMixedClassmapCorrectly(): void
    {
        $handlers = $this->discovery->discover(
            [
                AnnotatedHandler::class => '/fake/path.php',
                AnnotatedOverrideHandler::class => '/fake/path.php',
                UnannotatedHandler::class => '/fake/path.php',
                AbstractCommandHandler::class => '/fake/path.php',
            ],
            ['NashGao\\InteractiveShell\\'],
            fn(string $class): CommandHandlerInterface => new $class(),
        );

        // Only AnnotatedHandler and AnnotatedOverrideHandler should be discovered
        $this->assertCount(2, $handlers);

        $commands = array_map(
            fn(CommandHandlerInterface $h): string => $h->getCommand(),
            $handlers,
        );

        $this->assertContains('annotated', $commands);
        $this->assertContains('custom-cmd', $commands);
    }
}
