<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Product\QuickView;

use Contena\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('discovery')]
class MinimalQuickViewPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractProductDetailRoute $productRoute
    ) {
    }

}
