<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Message;

use DateTimeImmutable;
use NashGao\InteractiveShell\Message\Message;
use NashGao\InteractiveShell\Message\MessageFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageFormatter::class)]
final class MessageFormatterTest extends TestCase
{
    private MessageFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MessageFormatter();
    }

    public function testFormatDataMessage(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test payload',
            source: 'mqtt:publish',
            timestamp: new DateTimeImmutable('2024-01-15 10:30:00.123'),
            metadata: ['topic' => 'sensors/temp'],
        );

        $output = $this->formatter->format($message);

        self::assertStringContainsString('[DATA]', $output);
        self::assertStringContainsString('test payload', $output);
        self::assertStringContainsString('[sensors/temp]', $output);
        self::assertStringContainsString('<mqtt:publish>', $output);
    }

    public function testFormatSystemMessage(): void
    {
        $message = Message::system('Connection established');

        $output = $this->formatter->format($message);

        self::assertStringContainsString('[SYS]', $output);
        self::assertStringContainsString('Connection established', $output);
        // System source should not be shown
        self::assertStringNotContainsString('<system>', $output);
    }

    public function testFormatErrorMessage(): void
    {
        $message = Message::error('Connection failed');

        $output = $this->formatter->format($message);

        self::assertStringContainsString('[ERR]', $output);
        self::assertStringContainsString('Connection failed', $output);
    }

    public function testFormatInfoMessage(): void
    {
        $message = new Message(
            type: 'info',
            payload: 'Information',
            source: 'app',
            timestamp: new DateTimeImmutable(),
        );

        $output = $this->formatter->format($message);

        self::assertStringContainsString('[INFO]', $output);
    }

    public function testFormatUnknownType(): void
    {
        $message = new Message(
            type: 'custom',
            payload: 'test',
            source: 'app',
            timestamp: new DateTimeImmutable(),
        );

        $output = $this->formatter->format($message);

        self::assertStringContainsString('[CUSTOM]', $output);
    }

    public function testFormatWithTimestampDisabled(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test',
            source: 'mqtt',
            timestamp: new DateTimeImmutable('2024-01-15 10:30:00'),
        );

        $output = $this->formatter->setShowTimestamp(false)->format($message);

        self::assertStringNotContainsString('10:30', $output);
    }

    public function testFormatWithSourceDisabled(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test',
            source: 'mqtt:publish',
            timestamp: new DateTimeImmutable(),
        );

        $output = $this->formatter->setShowSource(false)->format($message);

        self::assertStringNotContainsString('<mqtt:publish>', $output);
    }

    public function testFormatWithColorDisabled(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test',
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
        );

        $output = $this->formatter->setColorEnabled(false)->format($message);

        // No ANSI escape codes
        self::assertStringNotContainsString("\033[", $output);
        self::assertStringContainsString('[DATA]', $output);
    }

    public function testFormatWithColorEnabled(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test',
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
        );

        $output = $this->formatter->setColorEnabled(true)->format($message);

        // Should contain ANSI escape codes
        self::assertStringContainsString("\033[", $output);
    }

    public function testFormatArrayPayload(): void
    {
        $message = new Message(
            type: 'data',
            payload: ['temperature' => 25.5, 'unit' => 'celsius'],
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
        );

        $output = $this->formatter->format($message);

        self::assertStringContainsString('temperature', $output);
        self::assertStringContainsString('25.5', $output);
    }

    public function testFormatNullPayload(): void
    {
        $message = new Message(
            type: 'data',
            payload: null,
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
        );

        $output = $this->formatter->format($message);

        self::assertStringContainsString('[DATA]', $output);
    }

    public function testFormatScalarPayload(): void
    {
        $message = new Message(
            type: 'data',
            payload: 42,
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
        );

        $output = $this->formatter->format($message);

        self::assertStringContainsString('42', $output);
    }

    public function testFormatCompact(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'short message',
            source: 'mqtt',
            timestamp: new DateTimeImmutable('2024-01-15 10:30:00'),
        );

        $output = $this->formatter->formatCompact($message);

        self::assertStringContainsString('[10:30:00]', $output);
        self::assertStringContainsString('D', $output); // First letter of 'data'
        self::assertStringContainsString('short message', $output);
    }

    public function testFormatCompactTruncatesLongPayload(): void
    {
        $longPayload = str_repeat('a', 100);
        $message = new Message(
            type: 'data',
            payload: $longPayload,
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
        );

        $output = $this->formatter->formatCompact($message);

        self::assertStringContainsString('...', $output);
        self::assertLessThan(100, strlen($output));
    }

    public function testSettersReturnSelfForChaining(): void
    {
        $result = $this->formatter
            ->setShowTimestamp(false)
            ->setShowSource(false)
            ->setColorEnabled(false);

        self::assertSame($this->formatter, $result);
    }

    public function testFormatShowsTimestampWithMilliseconds(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test',
            source: 'mqtt',
            timestamp: new DateTimeImmutable('2024-01-15 10:30:00.456'),
        );

        $output = $this->formatter->format($message);

        // H:i:s.v format includes milliseconds
        self::assertStringContainsString('10:30:00.456', $output);
    }

    public function testFormatWithNonStringTopic(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test',
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
            metadata: ['topic' => 123], // Non-string topic
        );

        $output = $this->formatter->format($message);

        // Should not crash, topic is skipped
        self::assertStringContainsString('[DATA]', $output);
        self::assertStringNotContainsString('[123]', $output);
    }

    public function testFormatWithMissingTopic(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test',
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
            metadata: [],
        );

        $output = $this->formatter->format($message);

        // No topic shown, no crash
        self::assertStringContainsString('[DATA]', $output);
    }
}
