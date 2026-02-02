<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Parser;

use NashGao\InteractiveShell\Parser\ParsedCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParsedCommand::class)]
final class ParsedCommandTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $command = new ParsedCommand(
            command: 'filter',
            arguments: ['topic:*', 'value'],
            options: ['format' => 'json', 'verbose' => true],
            raw: 'filter topic:* value --format=json --verbose',
            hasVerticalTerminator: false,
        );

        self::assertSame('filter', $command->command);
        self::assertSame(['topic:*', 'value'], $command->arguments);
        self::assertSame(['format' => 'json', 'verbose' => true], $command->options);
        self::assertSame('filter topic:* value --format=json --verbose', $command->raw);
        self::assertFalse($command->hasVerticalTerminator);
    }

    public function testEmptyReturnsEmptyInstance(): void
    {
        $command = ParsedCommand::empty();

        self::assertSame('', $command->command);
        self::assertSame([], $command->arguments);
        self::assertSame([], $command->options);
        self::assertSame('', $command->raw);
        self::assertFalse($command->hasVerticalTerminator);
    }

    public function testGetArgumentReturnsValueAtIndex(): void
    {
        $command = new ParsedCommand(
            command: 'echo',
            arguments: ['hello', 'world'],
            options: [],
            raw: 'echo hello world',
            hasVerticalTerminator: false,
        );

        self::assertSame('hello', $command->getArgument(0));
        self::assertSame('world', $command->getArgument(1));
    }

    public function testGetArgumentReturnsDefaultForMissingIndex(): void
    {
        $command = new ParsedCommand(
            command: 'echo',
            arguments: ['hello'],
            options: [],
            raw: 'echo hello',
            hasVerticalTerminator: false,
        );

        self::assertNull($command->getArgument(5));
        self::assertSame('default', $command->getArgument(5, 'default'));
    }

    public function testGetOptionReturnsValue(): void
    {
        $command = new ParsedCommand(
            command: 'list',
            arguments: [],
            options: ['format' => 'json', 'limit' => '10'],
            raw: 'list --format=json --limit=10',
            hasVerticalTerminator: false,
        );

        self::assertSame('json', $command->getOption('format'));
        self::assertSame('10', $command->getOption('limit'));
    }

    public function testGetOptionReturnsDefaultForMissingKey(): void
    {
        $command = new ParsedCommand(
            command: 'list',
            arguments: [],
            options: [],
            raw: 'list',
            hasVerticalTerminator: false,
        );

        self::assertNull($command->getOption('missing'));
        self::assertSame('default', $command->getOption('missing', 'default'));
    }

    public function testHasOptionReturnsTrueForExistingOption(): void
    {
        $command = new ParsedCommand(
            command: 'status',
            arguments: [],
            options: ['verbose' => true],
            raw: 'status --verbose',
            hasVerticalTerminator: false,
        );

        self::assertTrue($command->hasOption('verbose'));
    }

    public function testHasOptionReturnsFalseForMissingOption(): void
    {
        $command = new ParsedCommand(
            command: 'status',
            arguments: [],
            options: [],
            raw: 'status',
            hasVerticalTerminator: false,
        );

        self::assertFalse($command->hasOption('verbose'));
    }

    public function testVerticalTerminatorFlag(): void
    {
        $command = new ParsedCommand(
            command: 'list',
            arguments: [],
            options: [],
            raw: 'list\G',
            hasVerticalTerminator: true,
        );

        self::assertTrue($command->hasVerticalTerminator);
    }

    public function testReadonlyProperties(): void
    {
        $command = new ParsedCommand(
            command: 'test',
            arguments: ['arg1'],
            options: ['opt' => 'val'],
            raw: 'test arg1 --opt=val',
            hasVerticalTerminator: false,
        );

        // These should be accessible as readonly public properties
        self::assertSame('test', $command->command);
        self::assertSame(['arg1'], $command->arguments);
        self::assertSame(['opt' => 'val'], $command->options);
        self::assertSame('test arg1 --opt=val', $command->raw);
    }
}
