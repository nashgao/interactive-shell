<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Filter\Condition;

use NashGao\InteractiveShell\Filter\Condition\PatternCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PatternCondition::class)]
final class PatternConditionTest extends TestCase
{
    public function testLikeWithPercentWildcard(): void
    {
        $condition = new PatternCondition('name', 'LIKE', 'sensor%');
        $this->assertTrue($condition->evaluate(['name' => 'sensor_01']));
        $this->assertTrue($condition->evaluate(['name' => 'sensor']));
        $this->assertFalse($condition->evaluate(['name' => 'device_01']));
    }

    public function testLikeWithUnderscoreWildcard(): void
    {
        $condition = new PatternCondition('code', 'LIKE', 'A_C');
        $this->assertTrue($condition->evaluate(['code' => 'ABC']));
        $this->assertTrue($condition->evaluate(['code' => 'AXC']));
        $this->assertFalse($condition->evaluate(['code' => 'ABBC']));
    }

    public function testLikeIsCaseInsensitive(): void
    {
        $condition = new PatternCondition('name', 'LIKE', 'test%');
        $this->assertTrue($condition->evaluate(['name' => 'TestValue']));
    }

    public function testNotLikeNegatesMatch(): void
    {
        $condition = new PatternCondition('name', 'NOT LIKE', 'error%');
        $this->assertTrue($condition->evaluate(['name' => 'success']));
        $this->assertFalse($condition->evaluate(['name' => 'error_timeout']));
    }

    public function testRegexMatches(): void
    {
        $condition = new PatternCondition('value', 'REGEX', '/^\d{3}$/');
        $this->assertTrue($condition->evaluate(['value' => '123']));
        $this->assertFalse($condition->evaluate(['value' => '12']));
        $this->assertFalse($condition->evaluate(['value' => 'abc']));
    }

    public function testNullFieldReturnsFalse(): void
    {
        $condition = new PatternCondition('missing', 'LIKE', '%test%');
        $this->assertFalse($condition->evaluate(['other' => 'test']));
    }

    public function testNonStringFieldReturnsFalse(): void
    {
        $condition = new PatternCondition('value', 'LIKE', '%test%');
        $this->assertFalse($condition->evaluate(['value' => 42]));
    }

    public function testUnknownOperatorReturnsFalse(): void
    {
        $condition = new PatternCondition('value', 'GLOB', '*test*');
        $this->assertFalse($condition->evaluate(['value' => 'test']));
    }

    public function testToString(): void
    {
        $condition = new PatternCondition('name', 'LIKE', 'sensor%');
        $this->assertSame("name LIKE 'sensor%'", $condition->toString());
    }
}
