<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

/**
 * @template-extends StoreCollection<ImageStruct>
 */
class ImageCollection extends StoreCollection
{
    protected function getExpectedClass(): string
    {
        return ImageStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return ImageStruct::fromArray($element);
    }
}
