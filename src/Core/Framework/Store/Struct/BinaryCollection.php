<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

/**
 * @template-extends StoreCollection<BinaryStruct>
 */
class BinaryCollection extends StoreCollection
{
    protected function getExpectedClass(): string
    {
        return BinaryStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return BinaryStruct::fromArray($element);
    }
}
