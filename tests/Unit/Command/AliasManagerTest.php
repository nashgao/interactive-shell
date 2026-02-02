<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Command;

use NashGao\InteractiveShell\Command\AliasManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AliasManager::class)]
final class AliasManagerTest extends TestCase
{
    public function testConstructorWithDefaultAliases(): void
    {
        $manager = new AliasManager(['q' => 'exit', 'f' => 'filter']);

        self::assertTrue($manager->hasAlias('q'));
        self::assertTrue($manager->hasAlias('f'));
        self::assertFalse($manager->hasAlias('unknown'));
    }

    public function testConstructorWithNoAliases(): void
    {
        $manager = new AliasManager();

        self::assertSame([], $manager->getAliases());
    }

    public function testExpandSimpleAlias(): void
    {
        $manager = new AliasManager(['q' => 'exit']);

        self::assertSame('exit', $manager->expand('q'));
    }

    public function testExpandAliasWithArguments(): void
    {
        $manager = new AliasManager(['f' => 'filter']);

        self::assertSame('filter topic:sensors/*', $manager->expand('f topic:sensors/*'));
    }

    public function testExpandAliasWithMultipleArguments(): void
    {
        $manager = new AliasManager(['ls' => 'list --format=table']);

        self::assertSame('list --format=table -v --limit=10', $manager->expand('ls -v --limit=10'));
    }

    public function testExpandNonAliasReturnsOriginal(): void
    {
        $manager = new AliasManager(['q' => 'exit']);

        self::assertSame('help', $manager->expand('help'));
    }

    public function testExpandEmptyInputReturnsEmpty(): void
    {
        $manager = new AliasManager(['q' => 'exit']);

        self::assertSame('', $manager->expand(''));
    }

    public function testExpandTrimsInput(): void
    {
        $manager = new AliasManager(['q' => 'exit']);

        self::assertSame('exit', $manager->expand('  q  '));
    }

    public function testExpandOnlyMatchesFirstWord(): void
    {
        $manager = new AliasManager(['q' => 'exit']);

        // 'echo q' should not expand 'q' since it's not the first word
        self::assertSame('echo q', $manager->expand('echo q'));
    }

    public function testSetAliasAddsNewAlias(): void
    {
        $manager = new AliasManager();

        $manager->setAlias('s', 'status');

        self::assertTrue($manager->hasAlias('s'));
        self::assertSame('status', $manager->expand('s'));
    }

    public function testSetAliasOverwritesExisting(): void
    {
        $manager = new AliasManager(['s' => 'status']);

        $manager->setAlias('s', 'show');

        self::assertSame('show', $manager->expand('s'));
    }

    public function testSetAliasTrimsInputs(): void
    {
        $manager = new AliasManager();

        $manager->setAlias('  s  ', '  status  ');

        self::assertTrue($manager->hasAlias('s'));
        self::assertSame('status', $manager->expand('s'));
    }

    public function testSetAliasThrowsOnEmptyName(): void
    {
        $manager = new AliasManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Alias name cannot be empty');

        $manager->setAlias('', 'command');
    }

    public function testSetAliasThrowsOnEmptyCommand(): void
    {
        $manager = new AliasManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Command expansion cannot be empty');

        $manager->setAlias('alias', '');
    }

    public function testSetAliasThrowsOnBuiltInCommandConflict(): void
    {
        $manager = new AliasManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot create alias 'exit': conflicts with built-in shell command");

        $manager->setAlias('exit', 'quit');
    }

    public function testSetAliasThrowsOnAllBuiltInCommands(): void
    {
        $manager = new AliasManager();
        $builtIns = ['help', 'exit', 'quit', 'status', 'clear', 'connect'];

        foreach ($builtIns as $builtIn) {
            try {
                $manager->setAlias($builtIn, 'something');
                self::fail("Expected exception for built-in command: {$builtIn}");
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString($builtIn, $e->getMessage());
            }
        }
    }

    public function testRemoveAliasRemovesExisting(): void
    {
        $manager = new AliasManager(['q' => 'exit']);

        $result = $manager->removeAlias('q');

        self::assertTrue($result);
        self::assertFalse($manager->hasAlias('q'));
    }

    public function testRemoveAliasReturnsFalseForNonExistent(): void
    {
        $manager = new AliasManager();

        $result = $manager->removeAlias('nonexistent');

        self::assertFalse($result);
    }

    public function testGetAliasesReturnsAllAliases(): void
    {
        $aliases = ['q' => 'exit', 'f' => 'filter', 's' => 'status'];
        $manager = new AliasManager($aliases);

        self::assertSame($aliases, $manager->getAliases());
    }

    public function testIsBuiltInAliasReturnsTrueForDefaultAliases(): void
    {
        $manager = new AliasManager(['q' => 'exit']);

        self::assertTrue($manager->isBuiltInAlias('q'));
    }

    public function testIsBuiltInAliasReturnsFalseForUserAliases(): void
    {
        $manager = new AliasManager(['q' => 'exit']);
        $manager->setAlias('s', 'status');

        self::assertFalse($manager->isBuiltInAlias('s'));
    }

    public function testResetRestoresToDefaultAliases(): void
    {
        $manager = new AliasManager(['q' => 'exit']);
        $manager->setAlias('s', 'status');
        $manager->setAlias('f', 'filter');

        $manager->reset();

        self::assertTrue($manager->hasAlias('q'));
        self::assertFalse($manager->hasAlias('s'));
        self::assertFalse($manager->hasAlias('f'));
    }

    public function testResetWithNoDefaultsResultsInEmptyAliases(): void
    {
        $manager = new AliasManager();
        $manager->setAlias('s', 'status');

        $manager->reset();

        self::assertSame([], $manager->getAliases());
    }

    public function testNoRecursiveAliasExpansion(): void
    {
        // Aliases should only expand once, not recursively
        $manager = new AliasManager([
            'a' => 'b',
            'b' => 'c',
        ]);

        // 'a' expands to 'b', not 'c'
        self::assertSame('b', $manager->expand('a'));
    }

    public function testAliasExpansionWithWhitespaceInRest(): void
    {
        $manager = new AliasManager(['f' => 'filter']);

        // Implementation normalizes whitespace between command and arguments
        self::assertSame('filter arg1   arg2', $manager->expand('f   arg1   arg2'));
    }
}
