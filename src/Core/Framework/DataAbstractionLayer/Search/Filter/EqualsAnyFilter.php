<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Filter;


/**
 * @final
 */
class EqualsAnyFilter extends SingleFieldFilter
{
    /**
     * @param list<string|bool|float|int|null>|array<string, string> $value
     */
    public function __construct(
        protected readonly string $field,
        protected array $value = []
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return list<string|bool|float|int|null>|array<string, string>
     */
    public function getValue(): array
    {
        return $this->value;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
