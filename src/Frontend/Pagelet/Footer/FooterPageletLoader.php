<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Footer;

use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Contena\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\TreeItem;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Contena\Storefront\Event\RouteRequest\ShippingMethodRouteRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('discovery')]
class FooterPageletLoader implements FooterPageletLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NavigationLoaderInterface $navigationLoader,
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): FooterPagelet
    {
        $footerId = $salesChannelContext->getSalesChannel()->getFooterCategoryId();

        $tree = null;
        if ($footerId) {
            $tree = $this->navigationLoader->load($footerId, $salesChannelContext, $footerId);
        }

        $pagelet = new FooterPagelet(
            $tree,
            $this->loadServiceMenu($salesChannelContext),
            $this->loadPaymentMethods($request, $salesChannelContext),
            $this->loadShippingMethods($request, $salesChannelContext),
        );

        $this->eventDispatcher->dispatch(
            new FooterPageletLoadedEvent($pagelet, $salesChannelContext, $request)
        );

        return $pagelet;
    }

    private function loadServiceMenu(SalesChannelContext $context): CategoryCollection
    {
        $serviceId = $context->getSalesChannel()->getServiceCategoryId();

        if ($serviceId === null) {
            return new CategoryCollection();
        }

        $navigation = $this->navigationLoader->load($serviceId, $context, $serviceId, 1);

        return new CategoryCollection(array_map(static fn (TreeItem $treeItem) => $treeItem->getCategory(), $navigation->getTree()));
    }

    private function loadPaymentMethods(Request $request, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('footer-pagelet::payment-methods');

        $event = new PaymentMethodRouteRequestEvent($request, $request->duplicate(), $salesChannelContext, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->paymentMethodRoute
            ->load($event->getStoreApiRequest(), $salesChannelContext, $event->getCriteria())
            ->getPaymentMethods();
    }

    private function loadShippingMethods(Request $request, SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('footer-pagelet::shipping-methods');

        $event = new ShippingMethodRouteRequestEvent($request, $request->duplicate(), $salesChannelContext, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->shippingMethodRoute
            ->load($event->getStoreApiRequest(), $salesChannelContext, $event->getCriteria())
            ->getShippingMethods();
    }
}
