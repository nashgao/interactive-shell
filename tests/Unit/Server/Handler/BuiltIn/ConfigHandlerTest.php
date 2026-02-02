<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\ConfigHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigHandler::class)]
final class ConfigHandlerTest extends TestCase
{
    private ConfigHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ConfigHandler();
    }

    public function testGetCommandReturnsConfig(): void
    {
        $this->assertSame('config', $this->handler->getCommand());
    }

    public function testHandleWithoutArgumentsListsTopLevelKeys(): void
    {
        $context = $this->createMockContext([
            'app' => ['name' => 'TestApp'],
            'database' => ['host' => 'localhost'],
        ]);

        $command = new ParsedCommand(
            command: 'config',
            arguments: [],
            options: [],
            raw: 'config',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);
        $this->assertCount(2, $result->data);
    }

    public function testHandleWithKeyReturnsValue(): void
    {
        $context = $this->createMockContext([
            'database' => ['host' => 'localhost', 'port' => 3306],
        ]);

        $context->expects($this->once())
            ->method('has')
            ->with('database.host')
            ->willReturn(true);

        $context->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnCallback(function (string $key, mixed $default = null) {
                if ($key === 'database.host') {
                    return 'localhost';
                }
                return $default;
            });

        $command = new ParsedCommand(
            command: 'config',
            arguments: ['database.host'],
            options: [],
            raw: 'config database.host',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);
        $this->assertSame('database.host', $result->data['key']);
        $this->assertSame('localhost', $result->data['value']);
    }

    public function testHandleWithUnknownKeyReturnsFailure(): void
    {
        $context = $this->createMock(ContextInterface::class);
        $context->method('has')->willReturn(false);

        $command = new ParsedCommand(
            command: 'config',
            arguments: ['unknown.key'],
            options: [],
            raw: 'config unknown.key',
            hasVerticalTerminator: false
        );

        $result = $this->handler->handle($command, $context);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->error ?? '');
    }

    public function testGetDescriptionReturnsNonEmptyString(): void
    {
        $description = $this->handler->getDescription();

        $this->assertNotEmpty($description);
        $this->assertStringContainsString('config', strtolower($description));
    }

    public function testGetUsageReturnsExamples(): void
    {
        $usage = $this->handler->getUsage();

        $this->assertNotEmpty($usage);
        $this->assertGreaterThan(1, count($usage));
    }

    private function createMockContext(array $config): ContextInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $context = $this->createMock(ContextInterface::class);
        $context->method('getConfig')->willReturn($config);
        return $context;
    }
}
