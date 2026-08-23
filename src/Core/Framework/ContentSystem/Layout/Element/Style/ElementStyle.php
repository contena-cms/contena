<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Style;

/**
 * Per-element style state: a validated `option => breakpoint => scalar` map. An immutable DTO
 * (not a Struct), emitted as a raw array via ContentElement::jsonSerialize().
 *
 * Immutable by contract: the mutation subsystem aliases instances by reference, so changing one
 * in place would be unsafe.
 *
 * @internal
 */
final readonly class ElementStyle
{
    /**
     * @param array<string, string|int|float|bool|array<string, string|int|float|bool>> $values
     */
    public function __construct(
        private array $values = [],
    ) {
    }

    /**
     * @return array<string, string|int|float|bool|array<string, string|int|float|bool>>
     */
    public function toArray(): array
    {
        return $this->getValues();
    }

    /**
     * @return array<string, string|int|float|bool|array<string, string|int|float|bool>>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}
