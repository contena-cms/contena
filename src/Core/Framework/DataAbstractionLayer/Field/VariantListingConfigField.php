<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\VariantListingConfigFieldSerializer;

/**
 * @codeCoverageIgnore
 */
class VariantListingConfigField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        parent::__construct($storageName, $propertyName);
    }

    protected function getSerializerClass(): string
    {
        return VariantListingConfigFieldSerializer::class;
    }
}
