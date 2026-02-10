<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Filter;

use NashGao\InteractiveShell\Filter\FilterParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterParser::class)]
final class FilterParserTest extends TestCase
{
    private FilterParser $parser;

    protected function setUp(): void
    {
        $this->parser = FilterParser::create();
    }

    public function testParsesSimpleEquality(): void
    {
        $condition = $this->parser->parseCondition("status = 'active'");
        $this->assertTrue($condition->evaluate(['status' => 'active']));
        $this->assertFalse($condition->evaluate(['status' => 'inactive']));
    }

    public function testParsesNumericComparison(): void
    {
        $condition = $this->parser->parseCondition('temperature > 30');
        $this->assertTrue($condition->evaluate(['temperature' => 35]));
        $this->assertFalse($condition->evaluate(['temperature' => 25]));
    }

    public function testParsesLikePattern(): void
    {
        $condition = $this->parser->parseCondition("name like 'sensor%'");
        $this->assertTrue($condition->evaluate(['name' => 'sensor_01']));
        $this->assertFalse($condition->evaluate(['name' => 'device_01']));
    }

    public function testParsesAndCondition(): void
    {
        $condition = $this->parser->parseCondition("status = 'active' and value > 10");
        $this->assertTrue($condition->evaluate(['status' => 'active', 'value' => 20]));
        $this->assertFalse($condition->evaluate(['status' => 'active', 'value' => 5]));
    }

    public function testParsesOrCondition(): void
    {
        $condition = $this->parser->parseCondition("type = 'error' or type = 'warning'");
        $this->assertTrue($condition->evaluate(['type' => 'error']));
        $this->assertTrue($condition->evaluate(['type' => 'warning']));
        $this->assertFalse($condition->evaluate(['type' => 'info']));
    }

    public function testEmptyExpressionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parseCondition('');
    }

    public function testValidateReturnsValidForGoodExpression(): void
    {
        $result = $this->parser->validate("status = 'ok'");
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    public function testValidateReturnsInvalidForBadExpression(): void
    {
        $result = $this->parser->validate('');
        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    public function testGetRuleParserReturnsInstance(): void
    {
        $this->assertNotNull($this->parser->getRuleParser());
    }
}
