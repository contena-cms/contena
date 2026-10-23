<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Overview;

use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\AbstractUserRoute;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Event\RouteRequest\OrderRouteRequestEvent;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class AccountOverviewPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute,
        private readonly AbstractUserRoute $userRoute,
        private readonly NewsletterAccountPageletLoader $newsletterAccountPageletLoader,
        private readonly AbstractTranslator $translator,
        private readonly OrderBlogAvailabilityResolver $blogAvailabilityResolver
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws UserNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $channelContext, UserEntity $user): AccountOverviewPage
    {
        $page = $this->genericLoader->load($request, $channelContext);

        $page = AccountOverviewPage::createFrom($page);
        $page->setUser($this->loadUser($channelContext, $user));
        $this->setMetaInformation($page);

        $order = $this->loadNewestOrder($channelContext, $request);

        if ($order !== null) {
            $this->blogAvailabilityResolver->addAvailability([$order], $channelContext);

            $page->setNewestOrder($order);
        }

        $newslAccountPagelet = $this->newsletterAccountPageletLoader->load($request, $channelContext, $user);

        $page->setNewsletterAccountPagelet($newslAccountPagelet);

        $this->eventDispatcher->dispatch(
            new AccountOverviewPageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountOverviewPage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.overviewMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    private function loadNewestOrder(ChannelContext $context, Request $request): ?OrderEntity
    {
        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING))
            ->addAssociation('primaryOrderTransaction.paymentMethod')
            ->addAssociation('primaryOrderTransaction.stateMachineState')
            ->addAssociation('primaryOrderDelivery.shippingMethod')
            ->addAssociation('primaryOrderDelivery.stateMachineState')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('lineItems')
            ->addAssociation('lineItems.cover')
            ->addAssociation('lineItems.downloads.media')
            ->addAssociation('addresses')
            ->addAssociation('currency')
            ->addAssociation('stateMachineState')
            ->addAssociation('documents.documentType')
            ->addAssociation('documents.documentMediaFile')
            ->addAssociation('documents.documentA11yMediaFile')
            ->setLimit(1)
            ->addAssociation('orderUser');

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

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria)->getOrders()->getEntities()->first();
    }

    private function loadUser(ChannelContext $context, UserEntity $user): UserEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('requestedGroup');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('defaultShippingAddress.country');

        return $this->userRoute->load(new Request(), $context, $criteria, $user)->getUser();
    }
}
