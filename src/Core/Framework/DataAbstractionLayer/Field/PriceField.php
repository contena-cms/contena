<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;

class PriceField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        parent::__construct($storageName, $propertyName);
    }

    protected function getSerializerClass(): string
    {
        return PriceFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return PriceFieldAccessorBuilder::class;
    }
}
