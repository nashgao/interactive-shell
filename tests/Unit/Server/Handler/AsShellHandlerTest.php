<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler;

use NashGao\InteractiveShell\Server\Handler\AsShellHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(AsShellHandler::class)]
final class AsShellHandlerTest extends TestCase
{
    public function testDefaultConstructorSetsNullValues(): void
    {
        $attr = new AsShellHandler();

        $this->assertNull($attr->command);
        $this->assertNull($attr->description);
    }

    public function testConstructorAcceptsCommandOverride(): void
    {
        $attr = new AsShellHandler(command: 'db:status');

        $this->assertSame('db:status', $attr->command);
        $this->assertNull($attr->description);
    }

    public function testConstructorAcceptsDescriptionOverride(): void
    {
        $attr = new AsShellHandler(description: 'Show database status');

        $this->assertNull($attr->command);
        $this->assertSame('Show database status', $attr->description);
    }

    public function testConstructorAcceptsBothOverrides(): void
    {
        $attr = new AsShellHandler(command: 'db:status', description: 'Show database status');

        $this->assertSame('db:status', $attr->command);
        $this->assertSame('Show database status', $attr->description);
    }

    public function testAttributeTargetsClassOnly(): void
    {
        $reflection = new ReflectionClass(AsShellHandler::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);

        $attrInstance = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $attrInstance->flags);
    }

    public function testPropertiesAreReadonly(): void
    {
        $reflection = new ReflectionClass(AsShellHandler::class);

        $this->assertTrue($reflection->getProperty('command')->isReadOnly());
        $this->assertTrue($reflection->getProperty('description')->isReadOnly());
    }
}
