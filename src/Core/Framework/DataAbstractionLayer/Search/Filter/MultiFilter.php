<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @phpstan-ignore-next-line cannot be final, as it is extended, also designed to be used directly
 */
class MultiFilter extends Filter
{
    public const string CONNECTION_AND = 'AND';
    public const string CONNECTION_OR = 'OR';
    public const string CONNECTION_XOR = 'XOR';

    public const array VALID_OPERATORS = [
        self::CONNECTION_AND,
        self::CONNECTION_OR,
        self::CONNECTION_XOR,
    ];

    protected string $operator;

    /**
     * @param array<Filter> $queries
     */
    public function __construct(
        string $operator,
        protected array $queries = []
    ) {
        $this->operator = mb_strtoupper(trim($operator));

        if (!\in_array($this->operator, self::VALID_OPERATORS, true)) {
            throw new \InvalidArgumentException('Operator ' . $this->operator . ' not allowed');
        }
    }

    public function addQuery(Filter $query): self
    {
        $this->queries[] = $query;

        return $this;
    }

    /**
     * @return array<Filter>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getOperator(): string
    {
        if (!\in_array($this->operator, self::VALID_OPERATORS, true)) {
            throw new \InvalidArgumentException('Operator ' . $this->operator . ' not allowed');
        }

        return $this->operator;
    }

    public function getFields(): array
    {
        $fields = [];
        foreach ($this->queries as $query) {
            foreach ($query->getFields() as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }
}
