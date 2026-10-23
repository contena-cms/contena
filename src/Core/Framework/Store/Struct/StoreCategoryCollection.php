<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;

/**
 * @template-extends StoreCollection<StoreCategoryStruct>
 *
 * @codeCoverageIgnore
 */
class StoreCategoryCollection extends StoreCollection
{
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    protected function getExpectedClass(): ?string
    {
        return StoreCategoryStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return StoreCategoryStruct::fromArray($element);
    }
}
