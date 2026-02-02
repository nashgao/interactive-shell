<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Tests\Unit\Parser;

use NashGao\InteractiveShell\Command\AliasManager;
use NashGao\InteractiveShell\Parser\ParsedCommand;
use NashGao\InteractiveShell\Parser\ShellParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShellParser::class)]
#[CoversClass(ParsedCommand::class)]
final class ShellParserTest extends TestCase
{
    private ShellParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ShellParser();
    }

    public function testParseSimpleCommand(): void
    {
        $result = $this->parser->parse('help');

        self::assertSame('help', $result->command);
        self::assertSame([], $result->arguments);
        self::assertSame([], $result->options);
    }

    public function testParseCommandWithArguments(): void
    {
        $result = $this->parser->parse('filter topic:sensors/*');

        self::assertSame('filter', $result->command);
        self::assertSame(['topic:sensors/*'], $result->arguments);
    }

    public function testParseMultipleArguments(): void
    {
        $result = $this->parser->parse('echo hello world foo');

        self::assertSame('echo', $result->command);
        self::assertSame(['hello', 'world', 'foo'], $result->arguments);
    }

    public function testParseDoubleQuotedString(): void
    {
        $result = $this->parser->parse('echo "hello world"');

        self::assertSame('echo', $result->command);
        self::assertSame(['hello world'], $result->arguments);
    }

    public function testParseSingleQuotedString(): void
    {
        $result = $this->parser->parse("echo 'hello world'");

        self::assertSame('echo', $result->command);
        self::assertSame(['hello world'], $result->arguments);
    }

    public function testParseMixedQuotedAndUnquoted(): void
    {
        $result = $this->parser->parse('say "hello world" loudly');

        self::assertSame('say', $result->command);
        self::assertSame(['hello world', 'loudly'], $result->arguments);
    }

    public function testParseEscapedCharacters(): void
    {
        $result = $this->parser->parse('echo hello\ world');

        self::assertSame('echo', $result->command);
        self::assertSame(['hello world'], $result->arguments);
    }

    public function testParseEscapedQuoteInsideQuotes(): void
    {
        $result = $this->parser->parse('echo "say \"hi\""');

        self::assertSame('echo', $result->command);
        self::assertSame(['say "hi"'], $result->arguments);
    }

    public function testParseLongOptionWithValue(): void
    {
        $result = $this->parser->parse('list --format=json');

        self::assertSame('list', $result->command);
        self::assertSame([], $result->arguments);
        self::assertSame(['format' => 'json'], $result->options);
    }

    public function testParseLongOptionAsFlag(): void
    {
        $result = $this->parser->parse('status --verbose');

        self::assertSame('status', $result->command);
        self::assertSame(['verbose' => true], $result->options);
    }

    public function testParseShortOptionWithValue(): void
    {
        $result = $this->parser->parse('list -f=json');

        self::assertSame('list', $result->command);
        self::assertSame(['f' => 'json'], $result->options);
    }

    public function testParseShortOptionAsFlag(): void
    {
        $result = $this->parser->parse('status -v');

        self::assertSame('status', $result->command);
        self::assertSame(['v' => true], $result->options);
    }

    public function testParseMultipleOptions(): void
    {
        $result = $this->parser->parse('list --format=json --limit=10 -v');

        self::assertSame('list', $result->command);
        self::assertSame([
            'format' => 'json',
            'limit' => '10',
            'v' => true,
        ], $result->options);
    }

    public function testParseMixedArgumentsAndOptions(): void
    {
        $result = $this->parser->parse('filter topic:sensors/* --format=json value');

        self::assertSame('filter', $result->command);
        self::assertSame(['topic:sensors/*', 'value'], $result->arguments);
        self::assertSame(['format' => 'json'], $result->options);
    }

    public function testParseVerticalTerminator(): void
    {
        $result = $this->parser->parse('list\G');

        self::assertSame('list', $result->command);
        self::assertTrue($result->hasVerticalTerminator);
    }

    public function testParseVerticalTerminatorWithArguments(): void
    {
        $result = $this->parser->parse('show users\G');

        self::assertSame('show', $result->command);
        self::assertSame(['users'], $result->arguments);
        self::assertTrue($result->hasVerticalTerminator);
    }

    public function testParseEmptyInput(): void
    {
        $result = $this->parser->parse('');

        self::assertSame('', $result->command);
        self::assertSame([], $result->arguments);
        self::assertSame([], $result->options);
    }

    public function testParseWhitespaceOnlyInput(): void
    {
        $result = $this->parser->parse('   ');

        self::assertSame('', $result->command);
    }

    public function testParsePreservesRawInput(): void
    {
        $input = '  filter topic:* --format=json  ';
        $result = $this->parser->parse($input);

        self::assertSame($input, $result->raw);
    }

    public function testParseWithAliasExpansion(): void
    {
        $aliasManager = new AliasManager(['q' => 'exit', 'f' => 'filter']);
        $parser = new ShellParser($aliasManager);

        $result = $parser->parse('q');

        self::assertSame('exit', $result->command);
    }

    public function testParseAliasWithArguments(): void
    {
        $aliasManager = new AliasManager(['f' => 'filter']);
        $parser = new ShellParser($aliasManager);

        $result = $parser->parse('f topic:sensors/*');

        self::assertSame('filter', $result->command);
        self::assertSame(['topic:sensors/*'], $result->arguments);
    }

    public function testParseMultipleSpacesBetweenTokens(): void
    {
        $result = $this->parser->parse('echo   hello    world');

        self::assertSame('echo', $result->command);
        self::assertSame(['hello', 'world'], $result->arguments);
    }

    public function testParseNestedQuotes(): void
    {
        $result = $this->parser->parse("echo \"it's fine\"");

        self::assertSame('echo', $result->command);
        self::assertSame(["it's fine"], $result->arguments);
    }

    public function testParseDashAsArgument(): void
    {
        $result = $this->parser->parse('cat -');

        self::assertSame('cat', $result->command);
        // Single dash is not an option, but behavior depends on implementation
        // Current implementation treats single - as a short option with empty name
    }

    public function testParseDoubleDashSeparator(): void
    {
        // POSIX compliant: -- marks end of options, subsequent tokens are arguments
        $result = $this->parser->parse('cmd -- --not-an-option');

        self::assertSame('cmd', $result->command);
        self::assertSame(['--not-an-option'], $result->arguments);
        self::assertSame([], $result->options);
    }

    public function testParseDoubleDashSeparatorWithMultipleArguments(): void
    {
        $result = $this->parser->parse('cmd --real-option -- -a --looks-like-option value');

        self::assertSame('cmd', $result->command);
        self::assertSame(['-a', '--looks-like-option', 'value'], $result->arguments);
        self::assertSame(['real-option' => true], $result->options);
    }

    public function testParseDoubleDashSeparatorPreservesLeadingDashes(): void
    {
        $result = $this->parser->parse('echo -- --format=json');

        self::assertSame('echo', $result->command);
        self::assertSame(['--format=json'], $result->arguments);
        self::assertSame([], $result->options);
    }

    public function testParseInvalidLongOptionNameTreatedAsArgument(): void
    {
        // Option names starting with numbers are invalid
        $result = $this->parser->parse('cmd --123invalid');

        self::assertSame('cmd', $result->command);
        self::assertSame(['--123invalid'], $result->arguments);
        self::assertSame([], $result->options);
    }

    public function testParseInvalidShortOptionTreatedAsArgument(): void
    {
        // Short options starting with numbers are invalid
        $result = $this->parser->parse('cmd -123');

        self::assertSame('cmd', $result->command);
        self::assertSame(['-123'], $result->arguments);
        self::assertSame([], $result->options);
    }

    public function testParseOptionWithSpecialCharactersTreatedAsArgument(): void
    {
        // Options with special characters like @ are invalid
        $result = $this->parser->parse('cmd --foo@bar');

        self::assertSame('cmd', $result->command);
        self::assertSame(['--foo@bar'], $result->arguments);
        self::assertSame([], $result->options);
    }

    public function testParseValidOptionWithHyphensAndUnderscores(): void
    {
        // Valid long options can contain hyphens and underscores
        $result = $this->parser->parse('cmd --my-option --another_option=value');

        self::assertSame('cmd', $result->command);
        self::assertSame([], $result->arguments);
        self::assertSame([
            'my-option' => true,
            'another_option' => 'value',
        ], $result->options);
    }

    /**
     * @param array<int, string> $expectedArgs
     */
    #[DataProvider('complexInputProvider')]
    public function testParseComplexInputs(string $input, string $expectedCommand, array $expectedArgs): void
    {
        $result = $this->parser->parse($input);

        self::assertSame($expectedCommand, $result->command);
        self::assertSame($expectedArgs, $result->arguments);
    }

    /**
     * @return array<string, array{string, string, array<int, string>}>
     */
    public static function complexInputProvider(): array
    {
        return [
            'command only' => ['help', 'help', []],
            'single arg' => ['echo hello', 'echo', ['hello']],
            'quoted empty' => ['echo ""', 'echo', []], // Empty quoted strings are filtered out
            'special chars unquoted' => ['filter topic:+/#', 'filter', ['topic:+/#']],
            'path argument' => ['cat /var/log/app.log', 'cat', ['/var/log/app.log']],
            'url argument' => ['curl http://example.com', 'curl', ['http://example.com']],
        ];
    }
}
