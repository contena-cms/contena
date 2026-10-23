<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Footer;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\TreeItem;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Contena\Frontend\Event\RouteRequest\ShippingMethodRouteRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
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

    public function load(Request $request, ChannelContext $channelContext): FooterPagelet
    {
        $footerId = $channelContext->getChannel()->getFooterCategoryId();

        $tree = null;
        if ($footerId) {
            $tree = $this->navigationLoader->load($footerId, $channelContext, $footerId);
        }

        $pagelet = new FooterPagelet(
            $tree,
            $this->loadServiceMenu($channelContext),
            $this->loadPaymentMethods($request, $channelContext),
            $this->loadShippingMethods($request, $channelContext),
        );

        $this->eventDispatcher->dispatch(
            new FooterPageletLoadedEvent($pagelet, $channelContext, $request)
        );

        return $pagelet;
    }

    private function loadServiceMenu(ChannelContext $context): CategoryCollection
    {
        $serviceId = $context->getChannel()->getServiceCategoryId();

        if ($serviceId === null) {
            return new CategoryCollection();
        }

        $navigation = $this->navigationLoader->load($serviceId, $context, $serviceId, 1);

        return new CategoryCollection(array_map(static fn (TreeItem $treeItem) => $treeItem->getCategory(), $navigation->getTree()));
    }

    private function loadPaymentMethods(Request $request, ChannelContext $channelContext): PaymentMethodCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('footer-pagelet::payment-methods');

        $event = new PaymentMethodRouteRequestEvent($request, $request->duplicate(), $channelContext, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->paymentMethodRoute
            ->load($event->getStoreApiRequest(), $channelContext, $event->getCriteria())
            ->getPaymentMethods();
    }

    private function loadShippingMethods(Request $request, ChannelContext $channelContext): ShippingMethodCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('footer-pagelet::shipping-methods');

        $event = new ShippingMethodRouteRequestEvent($request, $request->duplicate(), $channelContext, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->shippingMethodRoute
            ->load($event->getStoreApiRequest(), $channelContext, $event->getCriteria())
            ->getShippingMethods();
    }
}
