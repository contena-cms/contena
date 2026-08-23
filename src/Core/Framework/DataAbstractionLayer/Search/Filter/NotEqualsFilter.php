<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @final
 */
class NotEqualsFilter extends NotFilter
{
    public function __construct(
        protected readonly string $field,
        protected readonly string|bool|float|int|null $value
    ) {
        parent::__construct(self::CONNECTION_AND, [
            new EqualsFilter($field, $value),
        ]);
    }

    public function getValue(): float|bool|int|string|null
    {
        return $this->value;
    }

    public function getField(): string
    {
        return $this->field;
    }
}
