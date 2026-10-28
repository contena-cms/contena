<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\FindVariant;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used the search for a matching product variant by given options
 */
#[Package('inventory')]
abstract class AbstractFindProductVariantRoute
{
    abstract public function getDecorated(): AbstractFindProductVariantRoute;

    abstract public function load(string $productId, Request $request, SalesChannelContext $context): FindProductVariantRouteResponse;
}
