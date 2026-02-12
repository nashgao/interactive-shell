<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Filter;

use NashGao\InteractiveShell\Filter\Condition\ConditionInterface;

/**
 * Represents a single filter clause for incremental building.
 *
 * Each clause has:
 * - An expression (the original SQL-like string)
 * - An operator (AND, OR, AND NOT, BASE for the first clause)
 * - A compiled condition for evaluation
 */
final readonly class FilterClause
{
    public const OPERATOR_BASE = 'BASE';
    public const OPERATOR_AND = 'AND';
    public const OPERATOR_OR = 'OR';
    public const OPERATOR_AND_NOT = 'AND NOT';

    /**
     * @param string $expression The original SQL-like expression
     * @param string $operator How this clause combines with previous (BASE, AND, OR, AND NOT)
     * @param ConditionInterface $condition Compiled condition for evaluation
     */
    public function __construct(
        public string $expression,
        public string $operator,
        public ConditionInterface $condition,
    ) {}

    /**
     * Check if this is the base (first) clause.
     */
    public function isBase(): bool
    {
        return $this->operator === self::OPERATOR_BASE;
    }

    /**
     * Get a string representation of this clause.
     */
    public function toString(): string
    {
        if ($this->isBase()) {
            return $this->expression;
        }

        return "{$this->operator} {$this->expression}";
    }
}
