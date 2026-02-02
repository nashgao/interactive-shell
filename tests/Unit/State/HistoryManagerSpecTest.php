<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\State;

use NashGao\InteractiveShell\State\HistoryManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Specification tests for HistoryManager behavior.
 *
 * These tests define WHAT HistoryManager SHOULD do from a consumer's perspective,
 * not what the implementation currently does.
 *
 * @internal
 */
#[CoversClass(HistoryManager::class)]
final class HistoryManagerSpecTest extends TestCase
{
    /**
     * Specification: History should not duplicate consecutive identical commands
     * (bash-like behavior).
     *
     * Expected behavior:
     * - Adding the same command twice in a row should only store it once
     * - This prevents pollution from repeated commands (like pressing up+enter)
     * - Non-consecutive duplicates ARE allowed (command A, B, A should store all three)
     */
    public function testHistoryDoesNotDuplicateConsecutiveCommands(): void
    {
        $historyManager = new HistoryManager(historyFile: sys_get_temp_dir() . '/test_history_' . uniqid());

        // Add first command
        $historyManager->add('ls -la');

        // Get initial history size
        $historyAfterFirst = $historyManager->getHistory();
        $sizeAfterFirst = count($historyAfterFirst);

        self::assertSame(
            1,
            $sizeAfterFirst,
            'History should contain one entry after first command'
        );

        // Add the exact same command again (consecutive duplicate)
        $historyManager->add('ls -la');

        // History should NOT grow
        $historyAfterDuplicate = $historyManager->getHistory();
        $sizeAfterDuplicate = count($historyAfterDuplicate);

        self::assertSame(
            1,
            $sizeAfterDuplicate,
            'History should still contain only one entry after consecutive duplicate'
        );

        self::assertSame(
            'ls -la',
            $historyAfterDuplicate[0] ?? '',
            'History entry should be the original command'
        );

        // Add a different command
        $historyManager->add('pwd');

        $historyAfterDifferent = $historyManager->getHistory();
        $sizeAfterDifferent = count($historyAfterDifferent);

        self::assertSame(
            2,
            $sizeAfterDifferent,
            'History should contain two entries after different command'
        );

        // Add the first command again (non-consecutive duplicate - should be allowed)
        $historyManager->add('ls -la');

        $historyAfterNonConsecutiveDupe = $historyManager->getHistory();
        $sizeAfterNonConsecutiveDupe = count($historyAfterNonConsecutiveDupe);

        self::assertSame(
            3,
            $sizeAfterNonConsecutiveDupe,
            'History should contain three entries - non-consecutive duplicates are allowed'
        );

        // Verify order: ls -la, pwd, ls -la
        self::assertSame('ls -la', $historyAfterNonConsecutiveDupe[0] ?? '');
        self::assertSame('pwd', $historyAfterNonConsecutiveDupe[1] ?? '');
        self::assertSame('ls -la', $historyAfterNonConsecutiveDupe[2] ?? '');
    }

    /**
     * Specification: History should truncate at maximum size to prevent unbounded growth.
     *
     * Expected behavior:
     * - When history exceeds configured maximum size, oldest entries are removed
     * - Most recent commands are always preserved
     * - History size never exceeds the configured limit
     * - Default limit should be reasonable (e.g., 1000 entries)
     */
    public function testHistoryTruncatesAtMaxSize(): void
    {
        // Create history manager with small max size for testing
        $maxSize = 10;
        $historyManager = new HistoryManager($maxSize, sys_get_temp_dir() . '/test_history_' . uniqid());

        // Add commands exceeding the max size
        $commandsToAdd = 25;
        for ($i = 1; $i <= $commandsToAdd; $i++) {
            $historyManager->add("command-{$i}");
        }

        // Verify history is capped at max size
        $history = $historyManager->getHistory();
        $actualSize = count($history);

        self::assertSame(
            $maxSize,
            $actualSize,
            "History size should be capped at {$maxSize} entries"
        );

        // Verify oldest entries were removed (first commands should be gone)
        self::assertNotContains(
            'command-1',
            $history,
            'Oldest command should be removed when limit exceeded'
        );

        self::assertNotContains(
            'command-2',
            $history,
            'Second oldest command should be removed'
        );

        // Verify newest entries are preserved
        self::assertContains(
            'command-25',
            $history,
            'Most recent command should be preserved'
        );

        self::assertContains(
            'command-24',
            $history,
            'Second most recent command should be preserved'
        );

        // Verify the history contains the last N commands
        $expectedLastCommand = "command-{$commandsToAdd}";
        $lastHistoryEntry = end($history);

        self::assertSame(
            $expectedLastCommand,
            $lastHistoryEntry,
            'Last history entry should be the most recently added command'
        );

        // Verify the first entry in truncated history is correct
        // Should be command-16 (25 - 10 + 1)
        $expectedFirstAfterTruncation = 'command-16';
        $firstHistoryEntry = reset($history);

        self::assertSame(
            $expectedFirstAfterTruncation,
            $firstHistoryEntry,
            'First history entry after truncation should be command-16'
        );
    }

    /**
     * Specification: Default history manager should have reasonable max size.
     *
     * This test verifies the default configuration is sensible for production use.
     */
    public function testDefaultHistoryHasReasonableMaxSize(): void
    {
        $historyManager = new HistoryManager(historyFile: sys_get_temp_dir() . '/test_history_' . uniqid());

        // Add many commands to test default limit
        // Default should be at least 100, typically 1000
        for ($i = 1; $i <= 150; $i++) {
            $historyManager->add("command-{$i}");
        }

        $history = $historyManager->getHistory();
        $historySize = count($history);

        self::assertGreaterThanOrEqual(
            100,
            $historySize,
            'Default history max size should be at least 100 entries'
        );

        self::assertLessThanOrEqual(
            2000,
            $historySize,
            'Default history max size should be reasonable (not exceeding 2000 entries)'
        );
    }
}
