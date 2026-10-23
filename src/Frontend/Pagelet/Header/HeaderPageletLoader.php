<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Header;

use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Currency\Channel\AbstractCurrencyRoute;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Language\Channel\AbstractLanguageRoute;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Frontend\Event\RouteRequest\CurrencyRouteRequestEvent;
use Contena\Frontend\Event\RouteRequest\LanguageRouteRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
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

    /**
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $context): HeaderPagelet
    {
        $channel = $context->getChannel();

        $navigation = $this->navigationLoader->load(
            $channel->getNavigationCategoryId(),
            $context,
            $channel->getNavigationCategoryId(),
            $channel->getNavigationCategoryDepth()
        );
        $languages = $this->getLanguages($context, $request);

        $page = new HeaderPagelet(
            $navigation,
            $languages,
            $this->getCurrencies($request, $context),
        );

        if (!Feature::isActive('v6.8.0.0')) {
            $contextLanguage = $languages->get($context->getLanguageId());
            if (!$contextLanguage) {
                throw ChannelException::languageNotFound($context->getLanguageId());
            }

            Feature::callSilentIfInactive('v6.8.0.0', static function () use ($contextLanguage, $context, $page): void {
                $page->setActiveLanguage($contextLanguage);
                $page->setActiveCurrency($context->getCurrency());
            });
        }

        $this->eventDispatcher->dispatch(new HeaderPageletLoadedEvent($page, $context, $request));

        return $page;
    }

    private function getLanguages(ChannelContext $context, Request $request): LanguageCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('header::languages');

        $criteria->addFilter(
            new EqualsFilter('language.channelDomains.channelId', $context->getChannelId())
        );
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $event = new LanguageRouteRequestEvent($request, new Request(), $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->languageRoute->load($event->getStoreApiRequest(), $context, $criteria)->getLanguages();
    }

    private function getCurrencies(Request $request, ChannelContext $context): CurrencyCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('header::currencies');

        $event = new CurrencyRouteRequestEvent($request, new Request(), $context);
        $this->eventDispatcher->dispatch($event);

        return $this->currencyRoute->load($event->getStoreApiRequest(), $context, $criteria)->getCurrencies();
    }
}
