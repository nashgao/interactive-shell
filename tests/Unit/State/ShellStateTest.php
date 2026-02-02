<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\State;

use NashGao\InteractiveShell\State\ShellState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShellState::class)]
final class ShellStateTest extends TestCase
{
    private string $tempSessionFile;

    protected function setUp(): void
    {
        $this->tempSessionFile = sys_get_temp_dir() . '/test_shell_session_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempSessionFile)) {
            unlink($this->tempSessionFile);
        }
    }

    public function testConstructorInitializesWithDefaults(): void
    {
        $state = new ShellState($this->tempSessionFile);

        self::assertSame('http://127.0.0.1:9501', $state->get('server_url'));
        self::assertSame('table', $state->get('default_format'));
        self::assertSame('shell> ', $state->get('prompt'));
    }

    public function testConstructorLoadsExistingSession(): void
    {
        $sessionData = [
            'server_url' => 'http://custom:8080',
            'custom_key' => 'custom_value',
        ];
        file_put_contents($this->tempSessionFile, json_encode($sessionData));

        $state = new ShellState($this->tempSessionFile);

        self::assertSame('http://custom:8080', $state->get('server_url'));
        self::assertSame('custom_value', $state->get('custom_key'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $state = new ShellState($this->tempSessionFile);

        self::assertNull($state->get('nonexistent'));
        self::assertSame('default', $state->get('nonexistent', 'default'));
    }

    public function testSetStoresValue(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $state->set('custom_key', 'custom_value');

        self::assertSame('custom_value', $state->get('custom_key'));
    }

    public function testGetContinuationPrompt(): void
    {
        $state = new ShellState($this->tempSessionFile);

        self::assertSame('...> ', $state->getContinuationPrompt());
    }

    public function testIsInMultiLineDefaultsFalse(): void
    {
        $state = new ShellState($this->tempSessionFile);

        self::assertFalse($state->isInMultiLine());
    }

    public function testProcessInputReturnsNormalInput(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $result = $state->processInput('help');

        self::assertSame('help', $result);
        self::assertFalse($state->isInMultiLine());
    }

    public function testProcessInputHandlesContinuationCharacter(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $result = $state->processInput('first line \\');

        self::assertNull($result);
        self::assertTrue($state->isInMultiLine());
    }

    public function testProcessInputCompletesMultiLineInput(): void
    {
        $state = new ShellState($this->tempSessionFile);

        // First line with continuation
        $result1 = $state->processInput('first \\');
        self::assertNull($result1);
        self::assertTrue($state->isInMultiLine());

        // Second line without continuation completes the input
        // Note: Implementation adds space between continuation lines
        $result2 = $state->processInput('second');
        self::assertSame('first second', $result2);
        self::assertFalse($state->isInMultiLine());
    }

    public function testProcessInputMultipleContinuationLines(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $state->processInput('line1 \\');
        $state->processInput('line2 \\');
        $result = $state->processInput('line3');

        // Implementation adds space between continuation lines
        self::assertSame('line1 line2 line3', $result);
    }

    public function testProcessInputEmptyInMultiLineResets(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $state->processInput('start \\');
        self::assertTrue($state->isInMultiLine());

        $result = $state->processInput('');

        self::assertNull($result);
        self::assertFalse($state->isInMultiLine());
    }

    public function testResetMultiLine(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $state->processInput('start \\');
        self::assertTrue($state->isInMultiLine());

        $state->resetMultiLine();

        self::assertFalse($state->isInMultiLine());
    }

    public function testReset(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $state->set('custom', 'value');
        $state->processInput('line \\');

        $state->reset();

        self::assertNull($state->get('custom'));
        self::assertFalse($state->isInMultiLine());
    }

    public function testRecordCommand(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $before = new \DateTimeImmutable();
        $state->recordCommand();
        $after = new \DateTimeImmutable();

        $metrics = $state->getSessionMetrics();

        self::assertSame(1, $metrics['commands_executed']);
        self::assertNotNull($metrics['last_command_time']);
    }

    public function testGetSessionMetrics(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $metrics = $state->getSessionMetrics();

        self::assertArrayHasKey('session_start', $metrics);
        self::assertArrayHasKey('session_duration', $metrics);
        self::assertArrayHasKey('commands_executed', $metrics);
        self::assertArrayHasKey('last_command_time', $metrics);
        self::assertArrayHasKey('total_commands_ever', $metrics);
        self::assertArrayHasKey('total_session_duration', $metrics);
    }

    public function testGetSessionDuration(): void
    {
        $state = new ShellState($this->tempSessionFile);

        $duration = $state->getSessionDuration();

        self::assertInstanceOf(\DateInterval::class, $duration);
    }

    public function testGetSessionStartTime(): void
    {
        $before = new \DateTimeImmutable();
        $state = new ShellState($this->tempSessionFile);
        $after = new \DateTimeImmutable();

        $startTime = $state->getSessionStartTime();

        self::assertGreaterThanOrEqual($before, $startTime);
        self::assertLessThanOrEqual($after, $startTime);
    }

    public function testSaveSession(): void
    {
        $state = new ShellState($this->tempSessionFile);
        $state->set('custom_key', 'custom_value');
        $state->recordCommand();

        $state->saveSession();

        self::assertFileExists($this->tempSessionFile);

        $content = file_get_contents($this->tempSessionFile);
        self::assertIsString($content);
        $saved = json_decode($content, true);
        self::assertIsArray($saved);
        self::assertSame('custom_value', $saved['custom_key']);
        self::assertArrayHasKey('last_saved', $saved);
    }

    public function testSaveSessionAccumulatesCommandCount(): void
    {
        // First session
        $state1 = new ShellState($this->tempSessionFile);
        $state1->recordCommand();
        $state1->recordCommand();
        $state1->saveSession();

        // Second session
        $state2 = new ShellState($this->tempSessionFile);
        $state2->recordCommand();
        $state2->saveSession();

        $content = file_get_contents($this->tempSessionFile);
        self::assertIsString($content);
        $saved = json_decode($content, true);
        self::assertIsArray($saved);
        self::assertSame(3, $saved['total_commands_ever']);
    }

    public function testLoadSessionHandlesInvalidJson(): void
    {
        file_put_contents($this->tempSessionFile, 'invalid json');

        $state = new ShellState($this->tempSessionFile);

        // Should load defaults instead of crashing
        self::assertSame('http://127.0.0.1:9501', $state->get('server_url'));
    }

    public function testLoadSessionHandlesNonArrayJson(): void
    {
        file_put_contents($this->tempSessionFile, '"just a string"');

        $state = new ShellState($this->tempSessionFile);

        // Should load defaults
        self::assertSame('http://127.0.0.1:9501', $state->get('server_url'));
    }
}
