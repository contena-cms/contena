<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractProductCloseoutFilterFactory
{
    abstract public function getDecorated(): AbstractProductCloseoutFilterFactory;

    abstract public function create(SalesChannelContext $context): MultiFilter;
}
