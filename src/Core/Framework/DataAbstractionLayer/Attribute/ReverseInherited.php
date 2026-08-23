<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ReverseInherited
{
    public function __construct(
        public string $propertyName
    ) {
    }
}
