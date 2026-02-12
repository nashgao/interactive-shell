<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Filter\Condition;

use NashGao\InteractiveShell\Filter\Condition\ComparisonCondition;
use NashGao\InteractiveShell\Filter\Condition\LogicalCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogicalCondition::class)]
final class LogicalConditionTest extends TestCase
{
    public function testAndRequiresAllTrue(): void
    {
        $condition = new LogicalCondition('AND', [
            new ComparisonCondition('a', '=', 1),
            new ComparisonCondition('b', '=', 2),
        ]);

        $this->assertTrue($condition->evaluate(['a' => 1, 'b' => 2]));
        $this->assertFalse($condition->evaluate(['a' => 1, 'b' => 99]));
    }

    public function testOrRequiresAtLeastOneTrue(): void
    {
        $condition = new LogicalCondition('OR', [
            new ComparisonCondition('a', '=', 1),
            new ComparisonCondition('b', '=', 2),
        ]);

        $this->assertTrue($condition->evaluate(['a' => 1, 'b' => 99]));
        $this->assertTrue($condition->evaluate(['a' => 99, 'b' => 2]));
        $this->assertFalse($condition->evaluate(['a' => 99, 'b' => 99]));
    }

    public function testNotNegatesFirstCondition(): void
    {
        $condition = new LogicalCondition('NOT', [
            new ComparisonCondition('status', '=', 'error'),
        ]);

        $this->assertTrue($condition->evaluate(['status' => 'ok']));
        $this->assertFalse($condition->evaluate(['status' => 'error']));
    }

    public function testNotWithEmptyConditionsReturnsFalse(): void
    {
        $condition = new LogicalCondition('NOT', []);
        $this->assertFalse($condition->evaluate([]));
    }

    public function testUnknownOperatorReturnsFalse(): void
    {
        $condition = new LogicalCondition('XOR', [
            new ComparisonCondition('a', '=', 1),
        ]);
        $this->assertFalse($condition->evaluate(['a' => 1]));
    }

    public function testToStringFormatsAndCorrectly(): void
    {
        $condition = new LogicalCondition('AND', [
            new ComparisonCondition('a', '=', 1),
            new ComparisonCondition('b', '>', 2),
        ]);
        $this->assertSame('(a = 1 AND b > 2)', $condition->toString());
    }

    public function testToStringFormatsNotCorrectly(): void
    {
        $condition = new LogicalCondition('NOT', [
            new ComparisonCondition('a', '=', 1),
        ]);
        $this->assertSame('NOT (a = 1)', $condition->toString());
    }

    public function testToStringNotEmptyConditions(): void
    {
        $condition = new LogicalCondition('NOT', []);
        $this->assertSame('NOT ()', $condition->toString());
    }
}
