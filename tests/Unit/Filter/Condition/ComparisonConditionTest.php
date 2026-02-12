<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Filter\Condition;

use NashGao\InteractiveShell\Filter\Condition\ComparisonCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComparisonCondition::class)]
final class ComparisonConditionTest extends TestCase
{
    public function testEqualityMatchesLoosely(): void
    {
        $condition = new ComparisonCondition('value', '=', 42);
        $this->assertTrue($condition->evaluate(['value' => 42]));
        $this->assertTrue($condition->evaluate(['value' => '42']));
    }

    public function testInequalityRejectsMatch(): void
    {
        $condition = new ComparisonCondition('value', '!=', 'error');
        $this->assertTrue($condition->evaluate(['value' => 'success']));
        $this->assertFalse($condition->evaluate(['value' => 'error']));
    }

    public function testGreaterThan(): void
    {
        $condition = new ComparisonCondition('temp', '>', 30);
        $this->assertTrue($condition->evaluate(['temp' => 31]));
        $this->assertFalse($condition->evaluate(['temp' => 30]));
        $this->assertFalse($condition->evaluate(['temp' => 29]));
    }

    public function testLessThan(): void
    {
        $condition = new ComparisonCondition('temp', '<', 30);
        $this->assertTrue($condition->evaluate(['temp' => 29]));
        $this->assertFalse($condition->evaluate(['temp' => 30]));
    }

    public function testGreaterThanOrEqual(): void
    {
        $condition = new ComparisonCondition('temp', '>=', 30);
        $this->assertTrue($condition->evaluate(['temp' => 30]));
        $this->assertTrue($condition->evaluate(['temp' => 31]));
        $this->assertFalse($condition->evaluate(['temp' => 29]));
    }

    public function testLessThanOrEqual(): void
    {
        $condition = new ComparisonCondition('temp', '<=', 30);
        $this->assertTrue($condition->evaluate(['temp' => 30]));
        $this->assertTrue($condition->evaluate(['temp' => 29]));
        $this->assertFalse($condition->evaluate(['temp' => 31]));
    }

    public function testMissingFieldReturnsFalse(): void
    {
        $condition = new ComparisonCondition('missing', '=', 'value');
        $this->assertFalse($condition->evaluate(['other' => 'value']));
    }

    public function testNestedFieldExtraction(): void
    {
        $condition = new ComparisonCondition('payload.temperature', '>', 25);
        $context = ['payload' => ['temperature' => 30]];
        $this->assertTrue($condition->evaluate($context));
    }

    public function testUnknownOperatorReturnsFalse(): void
    {
        $condition = new ComparisonCondition('x', '~', 1);
        $this->assertFalse($condition->evaluate(['x' => 1]));
    }

    #[DataProvider('toStringProvider')]
    public function testToStringFormatsCorrectly(mixed $value, string $expected): void
    {
        $condition = new ComparisonCondition('field', '=', $value);
        $this->assertSame($expected, $condition->toString());
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function toStringProvider(): array
    {
        return [
            'string' => ['hello', "field = 'hello'"],
            'int' => [42, 'field = 42'],
            'float' => [3.14, 'field = 3.14'],
            'bool true' => [true, 'field = true'],
            'bool false' => [false, 'field = false'],
            'null' => [null, 'field = null'],
        ];
    }
}
