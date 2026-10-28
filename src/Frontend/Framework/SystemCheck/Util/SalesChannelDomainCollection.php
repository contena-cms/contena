<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\SystemCheck\Util;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<SalesChannelDomain>
 */
#[Package('discovery')]
class SalesChannelDomainCollection extends Collection
{
    /**
     * @param list<SalesChannelDomain> $elements
     */
    public function __construct(
        array $elements,
    ) {
        $indexed = [];
        foreach ($elements as $element) {
            $indexed[$element->salesChannelId] = $element;
        }

        parent::__construct($indexed);
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelDomain::class;
    }
}
