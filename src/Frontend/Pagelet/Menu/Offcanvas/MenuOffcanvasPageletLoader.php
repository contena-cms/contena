<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Menu\Offcanvas;

use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a channel-api route to get or put data.
 */
class MenuOffcanvasPageletLoader implements MenuOffcanvasPageletLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NavigationLoaderInterface $navigationLoader,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $context): MenuOffcanvasPagelet
    {
        $navigationId = (string) $request->query->get('navigationId', $context->getChannel()->getNavigationCategoryId());
        if ($navigationId === '') {
            throw RoutingException::missingRequestParameter('navigationId');
        }

        $navigation = $this->navigationLoader->load($navigationId, $context, $navigationId, 1);

        $pagelet = new MenuOffcanvasPagelet($navigation);

        $this->eventDispatcher->dispatch(new MenuOffcanvasPageletLoadedEvent($pagelet, $context, $request));

        return $pagelet;
    }
}
