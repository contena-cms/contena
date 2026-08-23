<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

/**
 * @template-extends StoreCollection<FaqStruct>
 */
class FaqCollection extends StoreCollection
{
    protected function getExpectedClass(): string
    {
        return FaqStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return FaqStruct::fromArray($element);
    }
}
