<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\PurchaseLimit;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
abstract class AbstractProductPurchaseLimitRoute
{
    abstract public function getDecorated(): AbstractProductPurchaseLimitRoute;

    abstract public function readProductsPurchaseLimit(Request $request, SalesChannelContext $context): ProductPurchaseLimitRouteResponse;
}
