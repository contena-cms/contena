<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Order;

use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Exception\UserNotLoggedInException;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\Channel\AbstractOrderRoute;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Event\RouteRequest\OrderRouteRequestEvent;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 *
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
class AccountOrderDetailPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws UserNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $channelContext): AccountOrderDetailPage
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        if (!$channelContext->getUser()) {
            throw CartException::userNotLoggedIn();
        }

        $orderId = $request->attributes->getString('id', $request->query->getString('id'));

        if ($orderId === '') {
            throw RoutingException::missingRequestParameter('id');
        }

        $criteria = new Criteria([$orderId]);
        $criteria
            ->addAssociation('primaryOrderTransaction.paymentMethod')
            ->addAssociation('primaryOrderTransaction.stateMachineState')
            ->addAssociation('primaryOrderDelivery.shippingMethod')
            ->addAssociation('primaryOrderDelivery.stateMachineState')
            ->addAssociation('lineItems')
            ->addAssociation('orderUser')
            ->addAssociation('stateMachineState')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('lineItems.cover');

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria
                ->addAssociation('transactions.paymentMethod')
                ->addAssociation('transactions.stateMachineState')
                ->addAssociation('deliveries.stateMachineState');

            $criteria
                ->getAssociation('transactions')
                ->addSorting(new FieldSorting('createdAt'));
        }

        $apiRequest = $request->duplicate();

        $event = new OrderRouteRequestEvent($request, $apiRequest, $channelContext, $criteria);
        $this->eventDispatcher->dispatch($event);

        $result = $this->orderRoute
            ->load($event->getStoreApiRequest(), $channelContext, $criteria);

        $order = $result->getOrders()->getEntities()->first();

        if (!$order instanceof OrderEntity) {
            throw new NotFoundHttpException();
        }

        $page = AccountOrderDetailPage::createFrom($this->genericLoader->load($request, $channelContext));
        $page->setLineItems($order->getNestedLineItems());
        $page->setOrder($order);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $this->eventDispatcher->dispatch(
            new AccountOrderDetailPageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }
}
