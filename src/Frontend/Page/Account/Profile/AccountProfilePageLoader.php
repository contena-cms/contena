<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\Profile;

use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\Salutation\AbstractSalutationsSorter;
use Contena\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Contena\Core\System\Salutation\SalutationCollection;
use Contena\Storefront\Event\RouteRequest\SalutationRouteRequestEvent;
use Contena\Storefront\Page\GenericPageLoaderInterface;
use Contena\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountProfilePageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractSalutationRoute $salutationRoute,
        private readonly AbstractSalutationsSorter $salutationsSorter,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountProfilePage
    {
        if ($salesChannelContext->getCustomer() === null) {
            throw CartException::customerNotLoggedIn();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountProfilePage::createFrom($page);
        $this->setMetaInformation($page);

        $page->setSalutations($this->getSalutations($salesChannelContext, $request));

        $this->eventDispatcher->dispatch(
            new AccountProfilePageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountProfilePage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.profileMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    private function getSalutations(SalesChannelContext $context, Request $request): SalutationCollection
    {
        $event = new SalutationRouteRequestEvent($request, $request->duplicate(), $context, new Criteria());
        $this->eventDispatcher->dispatch($event);

        $salutations = $this->salutationRoute
            ->load($event->getStoreApiRequest(), $context, $event->getCriteria())
            ->getSalutations();

        return $this->salutationsSorter->sort($salutations);
    }
}
