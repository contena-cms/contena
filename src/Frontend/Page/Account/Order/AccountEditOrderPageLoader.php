<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Order;

use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Exception\UserNotLoggedInException;
use Contena\Core\Checkout\Cart\Order\OrderRestorer;
use Contena\Core\Checkout\Gateway\Channel\AbstractCheckoutGatewayRoute;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Checkout\Order\Channel\AbstractOrderRoute;
use Contena\Core\Checkout\Order\Channel\OrderRouteResponse;
use Contena\Core\Checkout\Order\Channel\OrderService;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Event\RouteRequest\OrderRouteRequestEvent;
use Contena\Frontend\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class AccountEditOrderPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
        private readonly OrderRestorer $orderRestorer,
        private readonly OrderService $orderService,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws UserNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws OrderException
     */
    public function load(Request $request, ChannelContext $channelContext): AccountEditOrderPage
    {
        if (!$channelContext->getUser() && !$request->query->getBoolean('deepLinkCode')) {
            throw CartException::userNotLoggedIn();
        }

        $page = $this->genericLoader->load($request, $channelContext);

        $page = AccountEditOrderPage::createFrom($page);
        $this->setMetaInformation($page);

        $orderRouteResponse = $this->getOrder($request, $channelContext);

        /** @var OrderEntity $order */
        $order = $orderRouteResponse->getOrders()->getEntities()->first();

        if ($this->isOrderCancelled($order)) {
            throw OrderException::orderCancelled($order->getId());
        }

        if ($this->isOrderPaid($order)) {
            throw OrderException::orderAlreadyPaid($order->getId());
        }

        $paymentMethods = $this->getPaymentMethods($channelContext, $request, $order);

        $page->setOrder($order);
        $page->setPaymentChangeable($this->isPaymentChangeable($orderRouteResponse, $page));
        $page->setPaymentMethods($paymentMethods);
        $page->setSelectedPaymentMethodId($this->getSelectedPaymentMethodId($request, $order, $paymentMethods));
        $page->setDeepLinkCode($request->query->get('deepLinkCode'));

        $this->eventDispatcher->dispatch(
            new AccountEditOrderPageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountEditOrderPage $page): void
    {
        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setRobots('noindex,follow');
        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.completePaymentMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    private function getOrder(Request $request, ChannelContext $context): OrderRouteResponse
    {
        $criteria = $this->createCriteria($request, $context);
        $apiRequest = $request->duplicate();
        $apiRequest->query->set('checkPromotion', true);

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria);
    }

    private function createCriteria(Request $request, ChannelContext $context): Criteria
    {
        $orderId = $request->attributes->getString('orderId');
        if ($orderId) {
            $criteria = new Criteria([$orderId]);
        } else {
            if (Feature::isActive('v6.8.0.0')) {
                throw OrderException::invalidUuid($orderId);
            }
            $criteria = new Criteria();
        }

        $criteria
            ->addAssociation('primaryOrderDelivery.shippingOrderAddress.salutation')
            ->addAssociation('primaryOrderDelivery.shippingOrderAddress.country')
            ->addAssociation('primaryOrderDelivery.shippingOrderAddress.countryState')
            ->addAssociation('primaryOrderDelivery.stateMachineState')
            ->addAssociation('primaryOrderTransaction.stateMachineState')
            ->addAssociation('lineItems.cover')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('billingAddress.salutation')
            ->addAssociation('billingAddress.country')
            ->addAssociation('stateMachineState')
            ->addAssociation('billingAddress.countryState');

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria
                ->addAssociation('deliveries.shippingOrderAddress.salutation')
                ->addAssociation('deliveries.shippingOrderAddress.country')
                ->addAssociation('deliveries.shippingOrderAddress.countryState')
                ->addAssociation('deliveries.stateMachineState')
                ->addAssociation('transactions.stateMachineState');
        }

        $this->orderRestorer->addRequiredAssociations($criteria);

        if ($context->getUser()) {
            $criteria->addFilter(new EqualsFilter('order.orderUser.userId', $context->getUserId()));
        } elseif ($request->query->get('deepLinkCode')) {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $request->query->get('deepLinkCode')));
        } else {
            throw CartException::userNotLoggedIn();
        }

        return $criteria;
    }

    private function getPaymentMethods(ChannelContext $context, Request $request, OrderEntity $order): PaymentMethodCollection
    {
        $routeRequest = $request->duplicate();
        $routeRequest->query->set('onlyAvailable', '1');

        $event = new PaymentMethodRouteRequestEvent($request, $routeRequest, $context);
        $this->eventDispatcher->dispatch($event);

        $restored = $this->orderRestorer->restore($order, $context->getContext());

        $options = $this->checkoutGatewayRoute->load($event->getStoreApiRequest(), $restored->cart, $restored->context);

        $paymentMethods = $options->getPaymentMethods()->filterByProperty('afterOrderEnabled', true);
        $paymentMethods->sortPaymentMethodsByPreference($context);

        return $paymentMethods;
    }

    private function getSelectedPaymentMethodId(Request $request, OrderEntity $order, PaymentMethodCollection $paymentMethods): ?string
    {
        $requestedPaymentMethodId = $request->query->get('paymentMethodId');

        if (\is_string($requestedPaymentMethodId) && $paymentMethods->has($requestedPaymentMethodId)) {
            return $requestedPaymentMethodId;
        }

        $transaction = $order->getPrimaryOrderTransaction();

        if (!Feature::isActive('v6.8.0.0')) {
            $transaction = $order->getTransactions()?->last();
        }

        return $transaction?->getPaymentMethodId();
    }

    private function isOrderCancelled(OrderEntity $order): bool
    {
        $stateMachineState = $order->getStateMachineState();

        if ($stateMachineState === null) {
            return false;
        }

        return $stateMachineState->getTechnicalName() === OrderStates::STATE_CANCELLED;
    }

    private function isOrderPaid(OrderEntity $order): bool
    {
        $transaction = $order->getPrimaryOrderTransaction();

        if (!Feature::isActive('v6.8.0.0')) {
            $transactions = $order->getTransactions();

            if ($transactions === null) {
                return false;
            }

            $transaction = $transactions->last();
        }

        if ($transaction === null) {
            return false;
        }

        $stateMachineState = $transaction->getStateMachineState();
        if ($stateMachineState === null) {
            return false;
        }

        return $stateMachineState->getTechnicalName() === OrderTransactionStates::STATE_PAID;
    }

    private function isPaymentChangeable(OrderRouteResponse $orderRouteResponse, AccountEditOrderPage $page): bool
    {
        $isChangeableByResponse = $orderRouteResponse->getPaymentsChangeable()[$page->getOrder()->getId()] ?? true;
        $isChangeableByTransactionState = $this->orderService->isPaymentChangeableByTransactionState($page->getOrder());

        return $isChangeableByResponse && $isChangeableByTransactionState;
    }
}
