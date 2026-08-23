<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

/**
 * @template-extends StoreCollection<StoreCategoryStruct>
 */
class StoreCategoryCollection extends StoreCollection
{
    protected function getExpectedClass(): string
    {
        return StoreCategoryStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return StoreCategoryStruct::fromArray($element);
    }
}
