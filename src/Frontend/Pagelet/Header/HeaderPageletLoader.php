<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Header;

use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Event\RouteRequest\CurrencyRouteRequestEvent;
use Contena\Storefront\Event\RouteRequest\LanguageRouteRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('discovery')]
class HeaderPageletLoader implements HeaderPageletLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCurrencyRoute $currencyRoute,
        private readonly AbstractLanguageRoute $languageRoute,
        private readonly NavigationLoaderInterface $navigationLoader
    ) {
    }


    private function getLanguages(SalesChannelContext $context, Request $request): LanguageCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('header::languages');

        $criteria->addFilter(
            new EqualsFilter('language.salesChannelDomains.salesChannelId', $context->getSalesChannelId())
        );
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $event = new LanguageRouteRequestEvent($request, new Request(), $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->languageRoute->load($event->getStoreApiRequest(), $context, $criteria)->getLanguages();
    }

    private function getCurrencies(Request $request, SalesChannelContext $context): CurrencyCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('header::currencies');

        $event = new CurrencyRouteRequestEvent($request, new Request(), $context);
        $this->eventDispatcher->dispatch($event);

        return $this->currencyRoute->load($event->getStoreApiRequest(), $context, $criteria)->getCurrencies();
    }
}
