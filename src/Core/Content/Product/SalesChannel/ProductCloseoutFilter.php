<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('inventory')]
class ProductCloseoutFilter extends NotFilter
{
    public function __construct()
    {
        parent::__construct(self::CONNECTION_AND, [
            new EqualsFilter('product.isCloseout', true),
            new EqualsFilter('product.available', false),
        ]);
    }
}
