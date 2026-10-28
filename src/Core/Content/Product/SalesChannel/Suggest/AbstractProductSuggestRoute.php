<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Suggest;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used for the product suggest in the page header
 */
#[Package('discovery')]
abstract class AbstractProductSuggestRoute
{
    abstract public function getDecorated(): AbstractProductSuggestRoute;

    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSuggestRouteResponse;
}
