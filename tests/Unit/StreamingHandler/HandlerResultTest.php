<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\StreamingHandler;

use NashGao\InteractiveShell\StreamingHandler\HandlerResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerResult::class)]
final class HandlerResultTest extends TestCase
{
    public function testSuccessFactory(): void
    {
        $result = HandlerResult::success('done');
        $this->assertTrue($result->success);
        $this->assertSame('done', $result->message);
        $this->assertFalse($result->shouldExit);
        $this->assertNull($result->pauseState);
    }

    public function testSuccessWithNullMessage(): void
    {
        $result = HandlerResult::success();
        $this->assertTrue($result->success);
        $this->assertNull($result->message);
    }

    public function testFailureFactory(): void
    {
        $result = HandlerResult::failure('something broke');
        $this->assertFalse($result->success);
        $this->assertSame('something broke', $result->message);
    }

    public function testExitFactory(): void
    {
        $result = HandlerResult::exit();
        $this->assertTrue($result->shouldExit);
    }

    public function testPauseFactory(): void
    {
        $result = HandlerResult::pause();
        $this->assertTrue($result->pauseState);
    }

    public function testResumeFactory(): void
    {
        $result = HandlerResult::resume();
        $this->assertFalse($result->pauseState);
    }

    public function testDefaultConstructorValues(): void
    {
        $result = new HandlerResult();
        $this->assertFalse($result->shouldExit);
        $this->assertNull($result->pauseState);
        $this->assertTrue($result->success);
        $this->assertNull($result->message);
    }
}
