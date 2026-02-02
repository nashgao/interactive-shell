<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Command;

use NashGao\InteractiveShell\Command\CommandResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandResult::class)]
final class CommandResultTest extends TestCase
{
    public function testSuccessFactoryCreatesSuccessResult(): void
    {
        $result = CommandResult::success(['key' => 'value'], 'Operation completed');

        self::assertTrue($result->success);
        self::assertSame(['key' => 'value'], $result->data);
        self::assertSame('Operation completed', $result->message);
        self::assertNull($result->error);
    }

    public function testSuccessWithoutDataOrMessage(): void
    {
        $result = CommandResult::success();

        self::assertTrue($result->success);
        self::assertNull($result->data);
        self::assertNull($result->message);
        self::assertNull($result->error);
    }

    public function testSuccessWithMetadata(): void
    {
        $result = CommandResult::success(['data'], null, ['duration' => 0.5]);

        self::assertTrue($result->success);
        self::assertSame(['duration' => 0.5], $result->metadata);
    }

    public function testFailureFactoryCreatesFailureResult(): void
    {
        $result = CommandResult::failure('Something went wrong');

        self::assertFalse($result->success);
        self::assertSame('Something went wrong', $result->error);
        self::assertNull($result->data);
        self::assertNull($result->message);
    }

    public function testFailureWithDataAndMetadata(): void
    {
        $result = CommandResult::failure('Error', ['context' => 'info'], ['code' => 500]);

        self::assertFalse($result->success);
        self::assertSame('Error', $result->error);
        self::assertSame(['context' => 'info'], $result->data);
        self::assertSame(['code' => 500], $result->metadata);
    }

    public function testFromResponseWithSuccessTrue(): void
    {
        $response = [
            'success' => true,
            'data' => ['items' => [1, 2, 3]],
            'message' => 'Fetched successfully',
            'metadata' => ['count' => 3],
        ];

        $result = CommandResult::fromResponse($response);

        self::assertTrue($result->success);
        self::assertSame(['items' => [1, 2, 3]], $result->data);
        self::assertSame('Fetched successfully', $result->message);
        self::assertSame(['count' => 3], $result->metadata);
    }

    public function testFromResponseWithSuccessFalse(): void
    {
        $response = [
            'success' => false,
            'error' => 'Not found',
        ];

        $result = CommandResult::fromResponse($response);

        self::assertFalse($result->success);
        self::assertSame('Not found', $result->error);
    }

    public function testFromResponseCollectsExtraFieldsAsData(): void
    {
        $response = [
            'success' => true,
            'id' => 123,
            'name' => 'test',
        ];

        $result = CommandResult::fromResponse($response);

        self::assertTrue($result->success);
        self::assertSame(['id' => 123, 'name' => 'test'], $result->data);
    }

    public function testFromResponseWithMissingSuccessDefaultsToFalse(): void
    {
        $response = ['error' => 'Missing success field'];

        $result = CommandResult::fromResponse($response);

        self::assertFalse($result->success);
    }

    public function testFromExceptionCreatesFailureResult(): void
    {
        $exception = new \RuntimeException('Database connection failed');

        $result = CommandResult::fromException($exception);

        self::assertFalse($result->success);
        self::assertSame('Database connection failed', $result->error);
        self::assertSame(\RuntimeException::class, $result->metadata['exception']);
        self::assertSame(2, $result->metadata['exit_code']);
    }

    public function testFromExceptionWithAdditionalMetadata(): void
    {
        $exception = new \InvalidArgumentException('Invalid input');

        $result = CommandResult::fromException($exception, ['input' => 'bad_value']);

        self::assertSame('bad_value', $result->metadata['input']);
        self::assertSame(\InvalidArgumentException::class, $result->metadata['exception']);
    }

    public function testGetExitCodeReturnsZeroForSuccess(): void
    {
        $result = CommandResult::success();

        self::assertSame(0, $result->getExitCode());
    }

    public function testGetExitCodeReturnsOneForFailure(): void
    {
        $result = CommandResult::failure('Error');

        self::assertSame(1, $result->getExitCode());
    }

    public function testWithMetadataMergesMetadata(): void
    {
        $original = CommandResult::success(null, null, ['a' => 1]);
        $updated = $original->withMetadata(['b' => 2]);

        // Original unchanged
        self::assertSame(['a' => 1], $original->metadata);

        // New result has merged metadata
        self::assertSame(['a' => 1, 'b' => 2], $updated->metadata);
    }

    public function testWithMessageAddsMessage(): void
    {
        $original = CommandResult::success(['data']);
        $updated = $original->withMessage('Done!');

        self::assertNull($original->message);
        self::assertSame('Done!', $updated->message);
    }

    public function testToArrayIncludesOnlyNonNullFields(): void
    {
        $result = CommandResult::success(['items' => []]);
        $array = $result->toArray();

        self::assertArrayHasKey('success', $array);
        self::assertArrayHasKey('data', $array);
        self::assertArrayNotHasKey('error', $array);
        self::assertArrayNotHasKey('message', $array);
        self::assertArrayNotHasKey('metadata', $array);
    }

    public function testToArrayIncludesAllSetFields(): void
    {
        $result = CommandResult::failure('Error', ['ctx'], ['code' => 1]);
        $array = $result->toArray();

        self::assertFalse($array['success']);
        self::assertSame('Error', $array['error']);
        self::assertSame(['ctx'], $array['data']);
        self::assertSame(['code' => 1], $array['metadata']);
    }

    public function testJsonSerializeReturnsToArray(): void
    {
        $result = CommandResult::success('data', 'msg');

        self::assertSame($result->toArray(), $result->jsonSerialize());
    }

    public function testResultIsImmutable(): void
    {
        $result = CommandResult::success(['original']);
        $modified = $result->withMessage('modified');

        // Original remains unchanged
        self::assertNull($result->message);
        self::assertSame(['original'], $result->data);

        // Modified has new message but same data
        self::assertSame('modified', $modified->message);
        self::assertSame(['original'], $modified->data);
    }
}
