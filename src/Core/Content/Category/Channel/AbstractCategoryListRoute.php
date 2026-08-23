<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractCategoryListRoute
{
    abstract public function getDecorated(): AbstractCategoryListRoute;

    abstract public function load(Criteria $criteria, ChannelContext $context): CategoryListRouteResponse;
}
