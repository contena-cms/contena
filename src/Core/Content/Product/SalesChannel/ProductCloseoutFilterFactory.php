<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductCloseoutFilterFactory extends AbstractProductCloseoutFilterFactory
{
    public function getDecorated(): AbstractProductCloseoutFilterFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(SalesChannelContext $context): MultiFilter
    {
        return new ProductCloseoutFilter();
    }
}
