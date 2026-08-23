<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

class ReverseInherited extends Flag
{
    public function __construct(protected string $propertyName)
    {
    }

    public function getReversedPropertyName(): string
    {
        return $this->propertyName;
    }

    public function parse(): \Generator
    {
        yield 'reversed_inherited' => $this->propertyName;
    }
}
