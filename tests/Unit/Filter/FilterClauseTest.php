<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Filter;

use NashGao\InteractiveShell\Filter\Condition\ComparisonCondition;
use NashGao\InteractiveShell\Filter\FilterClause;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterClause::class)]
final class FilterClauseTest extends TestCase
{
    public function testBaseClauseIsBase(): void
    {
        $clause = new FilterClause(
            "status = 'active'",
            FilterClause::OPERATOR_BASE,
            new ComparisonCondition('status', '=', 'active'),
        );

        $this->assertTrue($clause->isBase());
    }

    public function testAndClauseIsNotBase(): void
    {
        $clause = new FilterClause(
            'value > 10',
            FilterClause::OPERATOR_AND,
            new ComparisonCondition('value', '>', 10),
        );

        $this->assertFalse($clause->isBase());
    }

    public function testBaseClauseToStringReturnsExpression(): void
    {
        $clause = new FilterClause(
            "status = 'active'",
            FilterClause::OPERATOR_BASE,
            new ComparisonCondition('status', '=', 'active'),
        );

        $this->assertSame("status = 'active'", $clause->toString());
    }

    public function testNonBaseClauseToStringIncludesOperator(): void
    {
        $clause = new FilterClause(
            'value > 10',
            FilterClause::OPERATOR_AND,
            new ComparisonCondition('value', '>', 10),
        );

        $this->assertSame('AND value > 10', $clause->toString());
    }

    public function testOrClauseToString(): void
    {
        $clause = new FilterClause(
            "type = 'error'",
            FilterClause::OPERATOR_OR,
            new ComparisonCondition('type', '=', 'error'),
        );

        $this->assertSame("OR type = 'error'", $clause->toString());
    }

    public function testAndNotClauseToString(): void
    {
        $clause = new FilterClause(
            "type = 'debug'",
            FilterClause::OPERATOR_AND_NOT,
            new ComparisonCondition('type', '=', 'debug'),
        );

        $this->assertSame("AND NOT type = 'debug'", $clause->toString());
    }

    public function testPropertiesAreReadonly(): void
    {
        $condition = new ComparisonCondition('x', '=', 1);
        $clause = new FilterClause('x = 1', FilterClause::OPERATOR_BASE, $condition);

        $this->assertSame('x = 1', $clause->expression);
        $this->assertSame(FilterClause::OPERATOR_BASE, $clause->operator);
        $this->assertSame($condition, $clause->condition);
    }
}
