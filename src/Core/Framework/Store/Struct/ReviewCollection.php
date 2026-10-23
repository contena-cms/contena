<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Contena\Core\Framework\Log\Package;

/**
 * @template-extends StoreCollection<ReviewStruct>
 *
 * @codeCoverageIgnore
 */
class ReviewCollection extends StoreCollection
{
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    protected function getExpectedClass(): ?string
    {
        return ReviewStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return ReviewStruct::fromArray($element);
    }
}
