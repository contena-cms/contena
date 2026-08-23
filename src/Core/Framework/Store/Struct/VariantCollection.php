<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

/**
 * @template-extends StoreCollection<VariantStruct>
 */
class VariantCollection extends StoreCollection
{
    protected function getExpectedClass(): string
    {
        return VariantStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return VariantStruct::fromArray($element);
    }
}
