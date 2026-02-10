<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Filter;

use NashGao\InteractiveShell\Filter\FieldExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldExtractor::class)]
final class FieldExtractorTest extends TestCase
{
    public function testExtractsTopLevelField(): void
    {
        $this->assertSame('hello', FieldExtractor::extract(['name' => 'hello'], 'name'));
    }

    public function testExtractsNestedField(): void
    {
        $context = ['payload' => ['temperature' => 25.5]];
        $this->assertSame(25.5, FieldExtractor::extract($context, 'payload.temperature'));
    }

    public function testExtractsDeeplyNestedField(): void
    {
        $context = ['a' => ['b' => ['c' => 'deep']]];
        $this->assertSame('deep', FieldExtractor::extract($context, 'a.b.c'));
    }

    public function testReturnsNullForMissingPath(): void
    {
        $this->assertNull(FieldExtractor::extract(['a' => 1], 'b'));
    }

    public function testReturnsNullForPartialPath(): void
    {
        $this->assertNull(FieldExtractor::extract(['a' => 'string'], 'a.b'));
    }

    public function testReturnsNullForMissingNestedKey(): void
    {
        $context = ['a' => ['b' => 1]];
        $this->assertNull(FieldExtractor::extract($context, 'a.c'));
    }
}
