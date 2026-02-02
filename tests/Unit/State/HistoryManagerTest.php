<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\State;

use NashGao\InteractiveShell\State\HistoryManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HistoryManager::class)]
final class HistoryManagerTest extends TestCase
{
    private string $tempHistoryFile;

    protected function setUp(): void
    {
        $this->tempHistoryFile = sys_get_temp_dir() . '/test_shell_history_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempHistoryFile)) {
            unlink($this->tempHistoryFile);
        }
    }

    public function testConstructorCreatesEmptyHistory(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        self::assertSame([], $manager->getHistory());
    }

    public function testAddCommand(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('help');

        self::assertSame(['help'], $manager->getHistory());
    }

    public function testAddMultipleCommands(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('help');
        $manager->add('status');
        $manager->add('exit');

        self::assertSame(['help', 'status', 'exit'], $manager->getHistory());
    }

    public function testAddTrimsWhitespace(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('  help  ');

        self::assertSame(['help'], $manager->getHistory());
    }

    public function testAddIgnoresEmptyCommands(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('');
        $manager->add('   ');

        self::assertSame([], $manager->getHistory());
    }

    public function testAddIgnoresDuplicateConsecutiveCommands(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('help');
        $manager->add('help');
        $manager->add('help');

        self::assertSame(['help'], $manager->getHistory());
    }

    public function testAddAllowsNonConsecutiveDuplicates(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('help');
        $manager->add('status');
        $manager->add('help');

        self::assertSame(['help', 'status', 'help'], $manager->getHistory());
    }

    public function testAddRespectsMaxEntries(): void
    {
        $manager = new HistoryManager(maxEntries: 3, historyFile: $this->tempHistoryFile);

        $manager->add('one');
        $manager->add('two');
        $manager->add('three');
        $manager->add('four');

        self::assertSame(['two', 'three', 'four'], $manager->getHistory());
    }

    public function testGetLast(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('first');
        $manager->add('second');
        $manager->add('third');

        self::assertSame('third', $manager->getLast());
    }

    public function testGetLastReturnsNullForEmptyHistory(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        self::assertNull($manager->getLast());
    }

    public function testClear(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('help');
        $manager->add('status');

        $manager->clear();

        self::assertSame([], $manager->getHistory());
    }

    public function testPreviousNavigatesBackward(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('one');
        $manager->add('two');
        $manager->add('three');

        self::assertSame('three', $manager->previous());
        self::assertSame('two', $manager->previous());
        self::assertSame('one', $manager->previous());
    }

    public function testPreviousStopsAtBeginning(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('one');
        $manager->add('two');

        $manager->previous();
        $manager->previous();
        $manager->previous(); // Beyond beginning
        $manager->previous(); // Still at beginning

        self::assertSame('one', $manager->previous());
    }

    public function testPreviousReturnsNullForEmptyHistory(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        self::assertNull($manager->previous());
    }

    public function testNextNavigatesForward(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('one');
        $manager->add('two');
        $manager->add('three');

        // Navigate to beginning
        $manager->previous();
        $manager->previous();
        $manager->previous();

        // Now navigate forward
        self::assertSame('two', $manager->next());
        self::assertSame('three', $manager->next());
    }

    public function testNextReturnsNullAtEnd(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('one');
        $manager->add('two');

        // Position is at end after adding
        self::assertNull($manager->next());
    }

    public function testSaveAndLoad(): void
    {
        // Save history
        $manager1 = new HistoryManager(historyFile: $this->tempHistoryFile);
        $manager1->add('command1');
        $manager1->add('command2');
        $manager1->add('command3');
        $manager1->save();

        // Load in new manager
        $manager2 = new HistoryManager(historyFile: $this->tempHistoryFile);

        self::assertSame(['command1', 'command2', 'command3'], $manager2->getHistory());
    }

    public function testLoadRemovesDuplicatesFromFile(): void
    {
        // Create file with duplicates
        file_put_contents($this->tempHistoryFile, "one\none\ntwo\ntwo\nthree\n");

        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        self::assertSame(['one', 'two', 'three'], $manager->getHistory());
    }

    public function testLoadHandlesNonexistentFile(): void
    {
        $manager = new HistoryManager(historyFile: '/nonexistent/path/history');

        self::assertSame([], $manager->getHistory());
    }

    public function testLoadIgnoresEmptyLines(): void
    {
        file_put_contents($this->tempHistoryFile, "one\n\ntwo\n\n\nthree\n");

        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        self::assertSame(['one', 'two', 'three'], $manager->getHistory());
    }

    public function testLoadRespectsMaxEntries(): void
    {
        file_put_contents($this->tempHistoryFile, "one\ntwo\nthree\nfour\nfive\n");

        $manager = new HistoryManager(maxEntries: 3, historyFile: $this->tempHistoryFile);

        self::assertSame(['three', 'four', 'five'], $manager->getHistory());
    }

    public function testSaveCreatesDirectory(): void
    {
        $nestedPath = sys_get_temp_dir() . '/test_dir_' . uniqid() . '/history';

        try {
            $manager = new HistoryManager(historyFile: $nestedPath);
            $manager->add('test');
            $manager->save();

            self::assertFileExists($nestedPath);
        } finally {
            if (file_exists($nestedPath)) {
                unlink($nestedPath);
                rmdir(dirname($nestedPath));
            }
        }
    }

    public function testSaveSetsSecurePermissions(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);
        $manager->add('secret command');
        $manager->save();

        $perms = fileperms($this->tempHistoryFile) & 0777;
        self::assertSame(0600, $perms);
    }

    public function testPositionResetsAfterAdd(): void
    {
        $manager = new HistoryManager(historyFile: $this->tempHistoryFile);

        $manager->add('one');
        $manager->add('two');

        // Navigate backward
        $manager->previous();
        $manager->previous();

        // Add new command
        $manager->add('three');

        // Previous should return the new last command
        self::assertSame('three', $manager->previous());
    }
}
