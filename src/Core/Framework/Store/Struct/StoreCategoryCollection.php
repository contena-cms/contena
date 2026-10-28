<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Log\Package;

/**
 * @template-extends StoreCollection<StoreCategoryStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
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
