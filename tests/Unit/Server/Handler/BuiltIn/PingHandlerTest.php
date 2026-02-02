<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Server\Handler\BuiltIn;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Server\ContextInterface;
use NashGao\InteractiveShell\Server\Handler\BuiltIn\PingHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PingHandler::class)]
final class PingHandlerTest extends TestCase
{
    private PingHandler $handler;
    private ContextInterface $context;

    protected function setUp(): void
    {
        $this->handler = new PingHandler();
        $this->context = $this->createMock(ContextInterface::class);
    }

    public function testGetCommandReturnsPing(): void
    {
        $this->assertSame('ping', $this->handler->getCommand());
    }

    public function testHandleReturnsPong(): void
    {
        $command = ParsedCommand::empty();

        $result = $this->handler->handle($command, $this->context);

        $this->assertTrue($result->success);
        $this->assertSame('pong', $result->data);
    }

    public function testHandleIncludesTimestamp(): void
    {
        $command = ParsedCommand::empty();

        $result = $this->handler->handle($command, $this->context);

        $this->assertArrayHasKey('timestamp', $result->metadata);
        $this->assertIsInt($result->metadata['timestamp']);
    }

    public function testHandleIncludesMemoryUsage(): void
    {
        $command = ParsedCommand::empty();

        $result = $this->handler->handle($command, $this->context);

        $this->assertArrayHasKey('memory_usage', $result->metadata);
        $this->assertArrayHasKey('memory_peak', $result->metadata);
        $this->assertIsInt($result->metadata['memory_usage']);
        $this->assertIsInt($result->metadata['memory_peak']);
    }

    public function testGetDescriptionReturnsNonEmptyString(): void
    {
        $description = $this->handler->getDescription();

        $this->assertNotEmpty($description);
        $this->assertStringContainsString('pong', strtolower($description));
    }

    public function testGetUsageReturnsExamples(): void
    {
        $usage = $this->handler->getUsage();

        $this->assertNotEmpty($usage);
        $this->assertContains('ping', $usage);
    }
}
