<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Filter\Condition;

use NashGao\InteractiveShell\Filter\FieldExtractor;

/**
 * Pattern matching condition for LIKE and REGEX operations.
 *
 * Supports: LIKE, NOT LIKE, REGEX
 */
final readonly class PatternCondition implements ConditionInterface
{
    /**
     * @param string $field Field path to match against (e.g., 'payload.data')
     * @param string $operator Pattern operator (LIKE, NOT LIKE, REGEX)
     * @param string $pattern Pattern to match
     */
    public function __construct(
        public string $field,
        public string $operator,
        public string $pattern,
    ) {}

    public function evaluate(array $context): bool
    {
        $fieldValue = FieldExtractor::extract($context, $this->field);

        if ($fieldValue === null || !is_string($fieldValue)) {
            return false;
        }

        return match (strtoupper($this->operator)) {
            'LIKE' => $this->matchLike($fieldValue),
            'NOT LIKE' => !$this->matchLike($fieldValue),
            'REGEX' => $this->matchRegex($fieldValue),
            default => false,
        };
    }

    /**
     * Match SQL LIKE pattern (% = wildcard, _ = single char).
     */
    private function matchLike(string $value): bool
    {
        // Convert LIKE pattern to regex
        $pattern = str_replace(['%', '_'], ['.*', '.'], $this->pattern);
        $regex = '/^' . preg_quote($pattern, '/') . '$/i';
        $regex = str_replace(['\.\*', '\.'], ['.*', '.'], $regex);

        return preg_match($regex, $value) === 1;
    }

    /**
     * Match REGEX pattern.
     */
    private function matchRegex(string $value): bool
    {
        return preg_match($this->pattern, $value) === 1;
    }

    public function toString(): string
    {
        return "{$this->field} {$this->operator} '{$this->pattern}'";
    }
}
