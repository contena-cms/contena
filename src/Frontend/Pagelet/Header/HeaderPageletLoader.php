<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Header;

use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Language\Channel\AbstractLanguageRoute;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Frontend\Event\RouteRequest\LanguageRouteRequestEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a channel-api route to get or put data.
 */
class HeaderPageletLoader implements HeaderPageletLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractLanguageRoute $languageRoute,
        private readonly NavigationLoaderInterface $navigationLoader,
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
            $channel->getNavigationCategoryDepth(),
        );
        $languages = $this->getLanguages($context, $request);

        $page = new HeaderPagelet($navigation, $languages);

        $this->eventDispatcher->dispatch(new HeaderPageletLoadedEvent($page, $context, $request));

        return $page;
    }

    private function getLanguages(ChannelContext $context, Request $request): LanguageCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('header::languages');

        $criteria->addFilter(new EqualsFilter('language.channels.id', $context->getChannelId()));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $event = new LanguageRouteRequestEvent($request, new Request(), $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->languageRoute->load($event->getChannelApiRequest(), $context, $criteria)->getLanguages();
    }
}
