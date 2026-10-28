<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Detail;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
abstract class AbstractProductDetailRoute
{
    abstract public function getDecorated(): AbstractProductDetailRoute;

    abstract public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse;
}
