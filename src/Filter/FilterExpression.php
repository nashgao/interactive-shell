<?php

declare(strict_types=1);

namespace NashGao\InteractiveShell\Filter;

use NashGao\InteractiveShell\Filter\Condition\ConditionInterface;
use NashGao\InteractiveShell\Filter\Condition\LogicalCondition;
use NashGao\InteractiveShell\Message\Message;

/**
 * Enhanced filter expression with SQL-like syntax and incremental building.
 *
 * Supports:
 * - Full boolean logic (AND, OR, NOT)
 * - Multiple filters of same type
 * - Incremental clause building
 * - Time-based filtering
 *
 * Usage:
 *   $filter = new FilterExpression($parser);
 *   $filter->where("type = 'message' and qos = 1");
 *   $filter->addOr("type = 'event'");
 *   $filter->addNot("direction = 'debug'");
 *   if ($filter->matches($message)) { ... }
 *
 * Protocol-specific implementations should extend this class and override
 * buildContext() to extract fields from their message format.
 */
class FilterExpression
{
    /** @var array<FilterClause> */
    protected array $clauses = [];

    protected ?ConditionInterface $compiledCondition = null;

    public function __construct(
        protected readonly FilterParser $parser,
    ) {}

    /**
     * Set a new WHERE condition, replacing any existing filter.
     *
     * @param string $expression SQL-like filter expression
     * @return static
     * @throws \InvalidArgumentException If expression is invalid
     */
    public function where(string $expression): static
    {
        $expression = trim($expression);

        if ($expression === '') {
            return $this->clear();
        }

        $condition = $this->parser->parseCondition($expression);

        $this->clauses = [
            new FilterClause($expression, FilterClause::OPERATOR_BASE, $condition),
        ];
        $this->compiledCondition = null;

        return $this;
    }

    /**
     * Add an AND clause to existing filter.
     *
     * @param string $expression SQL-like filter expression
     * @return static
     * @throws \InvalidArgumentException If no base filter or expression invalid
     */
    public function addAnd(string $expression): static
    {
        return $this->addClause($expression, FilterClause::OPERATOR_AND);
    }

    /**
     * Add an OR clause to existing filter.
     *
     * @param string $expression SQL-like filter expression
     * @return static
     * @throws \InvalidArgumentException If no base filter or expression invalid
     */
    public function addOr(string $expression): static
    {
        return $this->addClause($expression, FilterClause::OPERATOR_OR);
    }

    /**
     * Add an AND NOT clause to existing filter.
     *
     * @param string $expression SQL-like filter expression
     * @return static
     * @throws \InvalidArgumentException If no base filter or expression invalid
     */
    public function addNot(string $expression): static
    {
        return $this->addClause($expression, FilterClause::OPERATOR_AND_NOT);
    }

    /**
     * Remove a clause matching the given expression.
     *
     * @param string $expression Expression to remove
     * @return static
     */
    public function remove(string $expression): static
    {
        $expression = trim($expression);

        $this->clauses = array_values(array_filter(
            $this->clauses,
            fn(FilterClause $clause) => $clause->expression !== $expression
        ));

        // If we removed the base clause and have remaining clauses, promote first to base
        if (!empty($this->clauses) && !$this->clauses[0]->isBase()) {
            $first = $this->clauses[0];
            $this->clauses[0] = new FilterClause(
                $first->expression,
                FilterClause::OPERATOR_BASE,
                $first->condition
            );
        }

        $this->compiledCondition = null;

        return $this;
    }

    /**
     * Check if a message matches the current filter.
     *
     * @param Message $message Message to check
     * @return bool True if message matches filter (or no filter set)
     */
    public function matches(Message $message): bool
    {
        if (empty($this->clauses)) {
            return true;
        }

        $condition = $this->compile();
        $context = $this->buildContext($message);

        return $this->evaluateCondition($condition, $context);
    }

    /**
     * Build context array from message for condition evaluation.
     *
     * Override this method in protocol-specific implementations
     * to extract fields from the message format.
     *
     * @param Message $message Message to extract context from
     * @return array<string, mixed> Context with extracted fields
     */
    protected function buildContext(Message $message): array
    {
        return [
            'type' => $message->type,
            'payload' => $message->payload,
            'source' => $message->source,
            'timestamp' => $message->timestamp->format(\DateTimeInterface::ATOM),
            'metadata' => $message->metadata,
        ];
    }

    /**
     * Evaluate a condition against context.
     *
     * Override this method in protocol-specific implementations
     * to add custom pattern matching (e.g., MQTT topic wildcards).
     *
     * @param ConditionInterface $condition Condition to evaluate
     * @param array<string, mixed> $context Message context
     * @return bool True if condition matches
     */
    protected function evaluateCondition(ConditionInterface $condition, array $context): bool
    {
        // Handle LogicalCondition recursively using our evaluateCondition
        if ($condition instanceof LogicalCondition) {
            return match (strtoupper($condition->operator)) {
                'AND' => $this->evaluateAnd($condition->conditions, $context),
                'OR' => $this->evaluateOr($condition->conditions, $context),
                'NOT' => !empty($condition->conditions)
                    && !$this->evaluateCondition($condition->conditions[0], $context),
                default => $condition->evaluate($context),
            };
        }

        // All other conditions evaluate directly
        return $condition->evaluate($context);
    }

    /**
     * Clear all filter clauses.
     *
     * @return static
     */
    public function clear(): static
    {
        $this->clauses = [];
        $this->compiledCondition = null;

        return $this;
    }

    /**
     * Get SQL-like representation of the filter.
     *
     * @return string SQL-like WHERE clause
     */
    public function toSql(): string
    {
        if (empty($this->clauses)) {
            return '';
        }

        $parts = [];
        foreach ($this->clauses as $clause) {
            if ($clause->isBase()) {
                $parts[] = $clause->expression;
            } else {
                $parts[] = strtolower($clause->operator) . ' ' . $clause->expression;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Check if any filters are set.
     */
    public function hasFilters(): bool
    {
        return !empty($this->clauses);
    }

    /**
     * Get all current clauses.
     *
     * @return array<FilterClause>
     */
    public function getClauses(): array
    {
        return $this->clauses;
    }

    /**
     * Create a clone of this filter expression.
     *
     * @return static
     */
    public function clone(): static
    {
        $clone = $this->createClone();
        $clone->clauses = $this->clauses;
        $clone->compiledCondition = $this->compiledCondition;

        return $clone;
    }

    /**
     * Create a new empty instance for cloning.
     *
     * Override in subclasses if additional constructor parameters are needed.
     *
     * @return static
     */
    protected function createClone(): static
    {
        // @phpstan-ignore-next-line Intentional for subclass support
        return new static($this->parser);
    }

    /**
     * Get string representation.
     */
    public function toString(): string
    {
        if (empty($this->clauses)) {
            return 'No filters (showing all)';
        }

        return $this->toSql();
    }

    /**
     * Get the underlying parser.
     */
    public function getParser(): FilterParser
    {
        return $this->parser;
    }

    /**
     * Add a clause with the given operator.
     */
    protected function addClause(string $expression, string $operator): static
    {
        if (empty($this->clauses)) {
            throw new \InvalidArgumentException(
                "Cannot add clause - no base filter set. Use 'filter where ...' first."
            );
        }

        $expression = trim($expression);
        $condition = $this->parser->parseCondition($expression);

        $this->clauses[] = new FilterClause($expression, $operator, $condition);
        $this->compiledCondition = null;

        return $this;
    }

    /**
     * Compile all clauses into a single condition.
     */
    protected function compile(): ConditionInterface
    {
        if ($this->compiledCondition !== null) {
            return $this->compiledCondition;
        }

        if (empty($this->clauses)) {
            throw new \LogicException('No clauses to compile');
        }

        if (count($this->clauses) === 1) {
            $this->compiledCondition = $this->clauses[0]->condition;
            return $this->compiledCondition;
        }

        // Build compound condition from clauses
        $result = $this->clauses[0]->condition;

        for ($i = 1; $i < count($this->clauses); $i++) {
            $clause = $this->clauses[$i];

            $result = match ($clause->operator) {
                FilterClause::OPERATOR_AND => new LogicalCondition('AND', [$result, $clause->condition]),
                FilterClause::OPERATOR_OR => new LogicalCondition('OR', [$result, $clause->condition]),
                FilterClause::OPERATOR_AND_NOT => new LogicalCondition('AND', [
                    $result,
                    new LogicalCondition('NOT', [$clause->condition]),
                ]),
                default => throw new \LogicException("Unknown operator: {$clause->operator}"),
            };
        }

        $this->compiledCondition = $result;
        return $this->compiledCondition;
    }

    /**
     * Evaluate AND condition.
     *
     * @param array<ConditionInterface> $conditions
     * @param array<string, mixed> $context
     */
    protected function evaluateAnd(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate OR condition.
     *
     * @param array<ConditionInterface> $conditions
     * @param array<string, mixed> $context
     */
    protected function evaluateOr(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if ($this->evaluateCondition($condition, $context)) {
                return true;
            }
        }
        return false;
    }
}
