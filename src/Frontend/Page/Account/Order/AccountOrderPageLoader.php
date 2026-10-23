<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Order;

use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Exception\UserNotLoggedInException;
use Contena\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Contena\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\Channel\AbstractOrderRoute;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Checkout\Order\OrderBlogAvailabilityResolver;
use Contena\Frontend\Event\RouteRequest\OrderRouteRequestEvent;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class AccountOrderPageLoader
{
    private const DEFAULT_LIMIT = 10;

    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute,
        private readonly AbstractTranslator $translator,
        private readonly OrderBlogAvailabilityResolver $blogAvailabilityResolver
    ) {
    }

    public function load(Request $request, ChannelContext $channelContext): AccountOrderPage
    {
        if (!$channelContext->getUser() && !$request->attributes->has('deepLinkCode')) {
            throw CartException::userNotLoggedIn();
        }

        $page = $this->genericLoader->load($request, $channelContext);

        $page = AccountOrderPage::createFrom($page);
        $this->setMetaInformation($page);

        $orders = $this->getOrders($request, $channelContext);

        $page->setOrders($orders);

        $page->setDeepLinkCode($request->attributes->get('deepLinkCode'));

        $this->eventDispatcher->dispatch(
            new AccountOrderPageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountOrderPage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.ordersMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    /**
     * @throws UserNotLoggedInException
     * @throws GuestNotAuthenticatedException
     * @throws WrongGuestCredentialsException
     *
     * @return EntitySearchResult<OrderCollection>
     */
    private function getOrders(Request $request, ChannelContext $context): EntitySearchResult
    {
        $criteria = $this->createCriteria($request);
        $apiRequest = $request->duplicate();

        // Add email and zipcode for guest user verification in order view
        if (RequestParamHelper::get($request, 'email', false) && RequestParamHelper::get($request, 'zipcode', false)) {
            $apiRequest->query->set('email', RequestParamHelper::get($request, 'email'));
            $apiRequest->query->set('zipcode', RequestParamHelper::get($request, 'zipcode'));
            $apiRequest->query->set('login', true);
        }

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        $responseStruct = $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria);

        $orders = $responseStruct->getOrders();

        $this->blogAvailabilityResolver->addAvailability($orders->getEntities(), $context);

        return $orders;
    }

    private function createCriteria(Request $request): Criteria
    {
        $page = RequestParamHelper::get($request, 'p');
        $page = $page ? (int) $page : 1;

        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('order.createdAt', FieldSorting::DESCENDING))
            ->addAssociation('primaryOrderTransaction.paymentMethod')
            ->addAssociation('primaryOrderTransaction.stateMachineState')
            ->addAssociation('primaryOrderDelivery.shippingMethod')
            ->addAssociation('primaryOrderDelivery.stateMachineState')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('orderUser.user')
            ->addAssociation('lineItems')
            ->addAssociation('lineItems.cover')
            ->addAssociation('lineItems.downloads.media')
            ->addAssociation('addresses')
            ->addAssociation('currency')
            ->addAssociation('stateMachineState')
            ->addAssociation('documents.documentType')
            ->addAssociation('documents.documentMediaFile')
            ->addAssociation('documents.documentA11yMediaFile')
            ->addAssociation('documents.documentFiles.media')
            ->setLimit(self::DEFAULT_LIMIT)
            ->setOffset(($page - 1) * self::DEFAULT_LIMIT)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria
                ->addAssociation('transactions.paymentMethod')
                ->addAssociation('transactions.stateMachineState')
                ->addAssociation('deliveries.stateMachineState');

            $criteria
                ->getAssociation('transactions')
                ->addSorting(new FieldSorting('createdAt'));
        }

        $criteria
            ->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING));

        if ($request->attributes->has('deepLinkCode')) {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $request->attributes->get('deepLinkCode')));
        }

        return $criteria;
    }
}
