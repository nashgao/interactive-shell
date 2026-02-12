<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Filter;

use NashGao\InteractiveShell\Filter\Condition\ConditionInterface;

/**
 * Parser for filter expressions.
 *
 * Delegates to RuleParser by wrapping expressions as pseudo-rules:
 * "SELECT * FROM '#' WHERE {expression}"
 *
 * Supports:
 * - Comparisons: field = value, field != value, field > value, etc.
 * - Patterns: field like 'pattern', field not like 'pattern'
 * - Logic: condition and condition, condition or condition, not condition
 * - Grouping: (condition) and (condition)
 * - Time expressions: timestamp > now() - interval '5m'
 */
final class FilterParser
{
    public function __construct(
        private readonly RuleParser $ruleParser,
    ) {}

    /**
     * Create a new FilterParser with a default RuleParser.
     */
    public static function create(): self
    {
        return new self(new RuleParser());
    }

    /**
     * Parse a filter expression into a Condition.
     *
     * @param string $expression SQL-like filter expression
     * @return ConditionInterface Parsed condition
     * @throws \InvalidArgumentException If expression syntax is invalid
     */
    public function parseCondition(string $expression): ConditionInterface
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new \InvalidArgumentException('Empty filter expression');
        }

        // Pre-process time expressions
        $expression = $this->preprocessTimeExpressions($expression);

        // Wrap as pseudo-SQL for RuleParser
        $pseudoSql = "SELECT * FROM '#' WHERE {$expression}";

        try {
            $parsed = $this->ruleParser->parse($pseudoSql);

            if ($parsed['where'] === null) {
                throw new \InvalidArgumentException('Failed to parse condition');
            }

            return $parsed['where'];
        } catch (\InvalidArgumentException $e) {
            throw $this->enhanceErrorMessage($e, $expression);
        }
    }

    /**
     * Validate a filter expression without throwing.
     *
     * @param string $expression Expression to validate
     * @return array{valid: bool, error: string|null}
     */
    public function validate(string $expression): array
    {
        try {
            $this->parseCondition($expression);
            return ['valid' => true, 'error' => null];
        } catch (\InvalidArgumentException $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pre-process time expressions to make them evaluable.
     *
     * Converts:
     * - now() - interval '5m' → actual timestamp
     * - timestamp > '10:30' → timestamp comparison with today's date
     * - timestamp between '10:00' and '11:00' → compound condition
     */
    private function preprocessTimeExpressions(string $expression): string
    {
        // Handle now() - interval 'Xm/h/s' patterns
        $expression = preg_replace_callback(
            "/now\(\)\s*-\s*interval\s+'(\d+)([smh])'/i",
            function (array $matches): string {
                $amount = (int) $matches[1];
                $unit = strtolower($matches[2]);

                $seconds = match ($unit) {
                    's' => $amount,
                    'm' => $amount * 60,
                    'h' => $amount * 3600,
                    default => $amount,
                };

                $timestamp = time() - $seconds;
                return "'" . date('Y-m-d\TH:i:s', $timestamp) . "'";
            },
            $expression
        ) ?? $expression;

        // Handle now() alone
        $expression = preg_replace_callback(
            '/now\(\)/i',
            function (): string {
                return "'" . date('Y-m-d\TH:i:s') . "'";
            },
            $expression
        ) ?? $expression;

        // Handle time-only patterns like '10:30' (assumes today)
        $expression = preg_replace_callback(
            "/timestamp\s*(>|<|>=|<=|=)\s*'(\d{1,2}:\d{2}(?::\d{2})?)'/i",
            function (array $matches): string {
                $operator = $matches[1];
                $time = $matches[2];

                if (substr_count($time, ':') === 1) {
                    $time .= ':00';
                }

                $datetime = date('Y-m-d') . 'T' . $time;
                return "timestamp {$operator} '{$datetime}'";
            },
            $expression
        ) ?? $expression;

        // Handle BETWEEN for timestamps
        $expression = preg_replace_callback(
            "/timestamp\s+between\s+'([^']+)'\s+and\s+'([^']+)'/i",
            function (array $matches): string {
                $start = $this->normalizeTimestamp($matches[1]);
                $end = $this->normalizeTimestamp($matches[2]);
                return "(timestamp >= '{$start}' and timestamp <= '{$end}')";
            },
            $expression
        ) ?? $expression;

        return $expression;
    }

    /**
     * Normalize a timestamp string (add date if missing).
     */
    private function normalizeTimestamp(string $timestamp): string
    {
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $timestamp)) {
            if (substr_count($timestamp, ':') === 1) {
                $timestamp .= ':00';
            }
            return date('Y-m-d') . 'T' . $timestamp;
        }

        return $timestamp;
    }

    /**
     * Enhance error message with helpful suggestions.
     */
    private function enhanceErrorMessage(\InvalidArgumentException $e, string $expression): \InvalidArgumentException
    {
        $message = $e->getMessage();

        $typos = [
            'liike' => 'like',
            'likee' => 'like',
            'annd' => 'and',
            'orr' => 'or',
            'nott' => 'not',
            'wherre' => 'where',
        ];

        foreach ($typos as $typo => $correct) {
            if (stripos($expression, $typo) !== false) {
                return new \InvalidArgumentException(
                    "Unknown operator '{$typo}'. Did you mean '{$correct}'?"
                );
            }
        }

        if (preg_match('/like\s+([^\'"][^\s]+)/i', $expression, $matches)) {
            return new \InvalidArgumentException(
                "Pattern '{$matches[1]}' must be quoted. Use: like '{$matches[1]}'"
            );
        }

        return $e;
    }

    /**
     * Get the underlying RuleParser.
     */
    public function getRuleParser(): RuleParser
    {
        return $this->ruleParser;
    }
}
