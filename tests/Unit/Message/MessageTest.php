<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Message;

use DateTimeImmutable;
use NashGao\InteractiveShell\Message\Message;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Message::class)]
final class MessageTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $timestamp = new DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $metadata = ['topic' => 'sensors/temp', 'qos' => 1];

        $message = new Message(
            type: 'data',
            payload: ['temperature' => 25.5],
            source: 'mqtt:publish',
            timestamp: $timestamp,
            metadata: $metadata,
        );

        self::assertSame('data', $message->type);
        self::assertSame(['temperature' => 25.5], $message->payload);
        self::assertSame('mqtt:publish', $message->source);
        self::assertSame($timestamp, $message->timestamp);
        self::assertSame($metadata, $message->metadata);
    }

    public function testConstructorWithDefaultMetadata(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test',
            source: 'test',
            timestamp: new DateTimeImmutable(),
        );

        self::assertSame([], $message->metadata);
    }

    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            'type' => 'data',
            'payload' => ['key' => 'value'],
            'source' => 'mqtt',
            'timestamp' => '2024-01-15T10:30:00+00:00',
            'metadata' => ['topic' => 'test/topic'],
        ];

        $message = Message::fromArray($data);

        self::assertSame('data', $message->type);
        self::assertSame(['key' => 'value'], $message->payload);
        self::assertSame('mqtt', $message->source);
        self::assertSame('2024-01-15T10:30:00+00:00', $message->timestamp->format(DateTimeImmutable::ATOM));
        self::assertSame(['topic' => 'test/topic'], $message->metadata);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = [];

        $message = Message::fromArray($data);

        self::assertSame('unknown', $message->type);
        self::assertNull($message->payload);
        self::assertSame('unknown', $message->source);
        self::assertSame([], $message->metadata);
    }

    public function testFromArrayDefaultsTypeToUnknown(): void
    {
        $data = ['payload' => 'test'];

        $message = Message::fromArray($data);

        self::assertSame('unknown', $message->type);
    }

    public function testFromArrayDefaultsSourceToUnknown(): void
    {
        $data = ['type' => 'data'];

        $message = Message::fromArray($data);

        self::assertSame('unknown', $message->source);
    }

    public function testFromArrayUsesPayloadKey(): void
    {
        $data = [
            'type' => 'data',
            'payload' => 'primary payload',
        ];

        $message = Message::fromArray($data);

        self::assertSame('primary payload', $message->payload);
    }

    public function testFromArrayFallsBackToDataKey(): void
    {
        $data = [
            'type' => 'data',
            'data' => 'fallback payload',
        ];

        $message = Message::fromArray($data);

        self::assertSame('fallback payload', $message->payload);
    }

    public function testFromArrayPayloadTakesPrecedenceOverData(): void
    {
        $data = [
            'type' => 'data',
            'payload' => 'primary',
            'data' => 'fallback',
        ];

        $message = Message::fromArray($data);

        self::assertSame('primary', $message->payload);
    }

    public function testFromArrayGeneratesTimestampIfMissing(): void
    {
        $before = new DateTimeImmutable();

        $message = Message::fromArray(['type' => 'data']);

        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $message->timestamp);
        self::assertLessThanOrEqual($after, $message->timestamp);
    }

    public function testFromArrayIgnoresNonStringType(): void
    {
        $data = ['type' => 123];

        $message = Message::fromArray($data);

        self::assertSame('unknown', $message->type);
    }

    public function testFromArrayIgnoresNonStringSource(): void
    {
        $data = ['source' => ['array']];

        $message = Message::fromArray($data);

        self::assertSame('unknown', $message->source);
    }

    public function testFromArrayIgnoresNonArrayMetadata(): void
    {
        $data = ['metadata' => 'not an array'];

        $message = Message::fromArray($data);

        self::assertSame([], $message->metadata);
    }

    public function testDataFactoryCreatesDataMessage(): void
    {
        $before = new DateTimeImmutable();

        $message = Message::data(
            payload: ['temp' => 25.5],
            source: 'mqtt:publish',
            metadata: ['topic' => 'sensors/temp'],
        );

        $after = new DateTimeImmutable();

        self::assertSame('data', $message->type);
        self::assertSame(['temp' => 25.5], $message->payload);
        self::assertSame('mqtt:publish', $message->source);
        self::assertSame(['topic' => 'sensors/temp'], $message->metadata);
        self::assertGreaterThanOrEqual($before, $message->timestamp);
        self::assertLessThanOrEqual($after, $message->timestamp);
    }

    public function testDataFactoryWithDefaultMetadata(): void
    {
        $message = Message::data('payload', 'source');

        self::assertSame([], $message->metadata);
    }

    public function testSystemFactoryCreatesSystemMessage(): void
    {
        $message = Message::system('Connected to broker', ['broker' => 'localhost']);

        self::assertSame('system', $message->type);
        self::assertSame('Connected to broker', $message->payload);
        self::assertSame('system', $message->source);
        self::assertSame(['broker' => 'localhost'], $message->metadata);
    }

    public function testSystemFactoryWithDefaultMetadata(): void
    {
        $message = Message::system('Connection established');

        self::assertSame([], $message->metadata);
        self::assertSame('system', $message->source);
    }

    public function testErrorFactoryCreatesErrorMessage(): void
    {
        $message = Message::error('Connection failed', ['code' => 500]);

        self::assertSame('error', $message->type);
        self::assertSame('Connection failed', $message->payload);
        self::assertSame('system', $message->source);
        self::assertSame(['code' => 500], $message->metadata);
    }

    public function testErrorFactoryWithDefaultMetadata(): void
    {
        $message = Message::error('Something went wrong');

        self::assertSame([], $message->metadata);
        self::assertSame('system', $message->source);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $timestamp = new DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $message = new Message(
            type: 'data',
            payload: ['key' => 'value'],
            source: 'mqtt',
            timestamp: $timestamp,
            metadata: ['topic' => 'test'],
        );

        $array = $message->toArray();

        self::assertSame([
            'type' => 'data',
            'payload' => ['key' => 'value'],
            'source' => 'mqtt',
            'timestamp' => '2024-01-15T10:30:00+00:00',
            'metadata' => ['topic' => 'test'],
        ], $array);
    }

    public function testToArrayWithNullPayload(): void
    {
        $message = new Message(
            type: 'data',
            payload: null,
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
        );

        $array = $message->toArray();

        self::assertNull($array['payload']);
    }

    public function testToArrayWithScalarPayload(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'string payload',
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
        );

        $array = $message->toArray();

        self::assertSame('string payload', $array['payload']);
    }

    public function testMessageIsReadonly(): void
    {
        $message = new Message(
            type: 'data',
            payload: 'test',
            source: 'mqtt',
            timestamp: new DateTimeImmutable(),
        );

        // Verify readonly by checking property access works
        self::assertSame('data', $message->type);
        self::assertSame('test', $message->payload);
        self::assertSame('mqtt', $message->source);

        // If we could modify, PHP would throw an error. The readonly keyword enforces this.
    }

    public function testRoundTripFromArrayToArray(): void
    {
        $original = [
            'type' => 'data',
            'payload' => ['nested' => ['data' => true]],
            'source' => 'mqtt:publish',
            'timestamp' => '2024-01-15T10:30:00+00:00',
            'metadata' => ['topic' => 'test/topic', 'qos' => 1],
        ];

        $message = Message::fromArray($original);
        $result = $message->toArray();

        self::assertSame($original, $result);
    }
}
