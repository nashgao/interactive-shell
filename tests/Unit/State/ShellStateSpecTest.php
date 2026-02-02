<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\State;

use NashGao\InteractiveShell\State\ShellState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Specification tests for ShellState behavior.
 *
 * These tests define WHAT ShellState SHOULD do from a consumer's perspective,
 * not what the implementation currently does.
 *
 * @internal
 */
#[CoversClass(ShellState::class)]
final class ShellStateSpecTest extends TestCase
{
    /**
     * Specification: Multi-line input should accumulate lines ending with backslash
     * until a line without backslash is encountered.
     *
     * Expected behavior:
     * - Lines ending with `\` should be buffered
     * - isInMultiLine() should return true while buffering
     * - Final line (no backslash) should return complete command
     * - Buffer should be cleared after final line
     */
    public function testMultiLineBufferAccumulatesCorrectly(): void
    {
        $shellState = new ShellState();

        // First line with backslash - should start buffering
        $result1 = $shellState->processInput('echo "first line" \\');

        // Should be in multi-line mode
        self::assertTrue(
            $shellState->isInMultiLine(),
            'Shell should be in multi-line mode after line ending with backslash'
        );

        // Result should be null or empty while buffering
        self::assertNull(
            $result1,
            'Processing incomplete multi-line input should return null'
        );

        // Second line with backslash - should continue buffering
        $result2 = $shellState->processInput('  && echo "second line" \\');

        // Should still be in multi-line mode
        self::assertTrue(
            $shellState->isInMultiLine(),
            'Shell should remain in multi-line mode'
        );

        self::assertNull(
            $result2,
            'Processing incomplete multi-line input should return null'
        );

        // Final line without backslash - should return complete command
        $result3 = $shellState->processInput('  && echo "third line"');

        // Should no longer be in multi-line mode
        self::assertFalse(
            $shellState->isInMultiLine(),
            'Shell should exit multi-line mode after final line'
        );

        // Should return complete concatenated command
        self::assertNotNull(
            $result3,
            'Final line should return complete command'
        );

        self::assertStringContainsString(
            'first line',
            $result3,
            'Complete command should contain first line'
        );

        self::assertStringContainsString(
            'second line',
            $result3,
            'Complete command should contain second line'
        );

        self::assertStringContainsString(
            'third line',
            $result3,
            'Complete command should contain third line'
        );

        // Backslashes should be removed from continuation
        self::assertStringNotContainsString(
            '\\',
            $result3,
            'Line continuation backslashes should be removed'
        );
    }

    /**
     * Specification: Session state should persist across instances when using
     * the same session storage path.
     *
     * Expected behavior:
     * - Settings saved in one instance should be loadable in another
     * - Session data should survive ShellState destruction/recreation
     * - New instance with same session path should restore previous state
     */
    public function testSessionPersistsAcrossInstances(): void
    {
        $sessionPath = sys_get_temp_dir() . '/shell_state_test_' . uniqid() . '.json';

        // Create first instance and set some state
        $firstInstance = new ShellState($sessionPath);
        $firstInstance->set('test_var', 'test_value');
        $firstInstance->set('number_var', 42);

        // Save session (returns void, so we just ensure it doesn't throw)
        $firstInstance->saveSession();

        self::assertFileExists(
            $sessionPath,
            'Session file should be created after saveSession()'
        );

        // Destroy first instance (unset simulates end of execution)
        unset($firstInstance);

        // Create new instance with same session path
        // loadSession() is called automatically in constructor
        $secondInstance = new ShellState($sessionPath);

        // Verify state was restored
        self::assertEquals(
            'test_value',
            $secondInstance->get('test_var'),
            'String variable should persist across instances'
        );

        self::assertEquals(
            42,
            $secondInstance->get('number_var'),
            'Numeric variable should persist across instances'
        );

        // Cleanup
        @unlink($sessionPath);
    }

    protected function tearDown(): void
    {
        // Clean up any test session files
        $pattern = sys_get_temp_dir() . '/shell_state_test_*.json';
        $files = glob($pattern);

        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }
}
