<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Filter;

use NashGao\InteractiveShell\Filter\Condition\ComparisonCondition;
use NashGao\InteractiveShell\Filter\Condition\LogicalCondition;
use NashGao\InteractiveShell\Filter\Condition\PatternCondition;
use NashGao\InteractiveShell\Filter\RuleParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleParser::class)]
final class RuleParserTest extends TestCase
{
    private RuleParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RuleParser();
    }

    public function testParsesSelectWildcard(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'sensor/#'");
        $this->assertSame(['*'], $result['select']);
    }

    public function testParsesSelectSpecificFields(): void
    {
        $result = $this->parser->parse("SELECT temperature, humidity FROM 'sensor/#'");
        $this->assertSame(['temperature', 'humidity'], $result['select']);
    }

    public function testParsesFromTopic(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'sensor/#'");
        $this->assertSame('sensor/#', $result['from']);
    }

    public function testParsesWithoutWhereClause(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'topic'");
        $this->assertNull($result['where']);
    }

    public function testParsesWhereWithComparison(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'topic' WHERE temperature > 30");
        $this->assertInstanceOf(ComparisonCondition::class, $result['where']);
    }

    public function testParsesWhereWithLike(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'topic' WHERE name like 'test%'");
        $this->assertInstanceOf(PatternCondition::class, $result['where']);
    }

    public function testParsesWhereWithAnd(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'topic' WHERE a > 1 and b < 10");
        $this->assertInstanceOf(LogicalCondition::class, $result['where']);
    }

    public function testParsesWhereWithOr(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'topic' WHERE a = 1 or b = 2");
        $this->assertInstanceOf(LogicalCondition::class, $result['where']);
    }

    public function testParsesNot(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'topic' WHERE NOT a = 1");
        $this->assertInstanceOf(LogicalCondition::class, $result['where']);
    }

    public function testParsesStringValues(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'topic' WHERE status = 'active'");
        $this->assertInstanceOf(ComparisonCondition::class, $result['where']);
        $this->assertTrue($result['where']->evaluate(['status' => 'active']));
    }

    public function testParsesNumericValues(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'topic' WHERE value = 42");
        $this->assertInstanceOf(ComparisonCondition::class, $result['where']);
        $this->assertTrue($result['where']->evaluate(['value' => 42]));
    }

    public function testParsesFloatValues(): void
    {
        $result = $this->parser->parse("SELECT * FROM 'topic' WHERE value = 3.14");
        $this->assertInstanceOf(ComparisonCondition::class, $result['where']);
        $this->assertTrue($result['where']->evaluate(['value' => 3.14]));
    }

    public function testInvalidSelectThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse("INVALID QUERY");
    }

    public function testUnquotedFromThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse("SELECT * FROM unquoted");
    }

    public function testInvalidConditionSyntaxThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse("SELECT * FROM 'topic' WHERE invalid syntax here");
    }
}
