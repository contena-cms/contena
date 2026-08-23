<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Footer;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\TreeItem;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a channel-api route to get or put data.
 */
class FooterPageletLoader implements FooterPageletLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NavigationLoaderInterface $navigationLoader,
    ) {
    }

    public function load(Request $request, ChannelContext $channelContext): FooterPagelet
    {
        $footerId = $channelContext->getChannel()->getFooterCategoryId();

        $tree = null;
        if ($footerId) {
            $tree = $this->navigationLoader->load($footerId, $channelContext, $footerId);
        }

        $pagelet = new FooterPagelet($tree, $this->loadServiceMenu($channelContext));

        $this->eventDispatcher->dispatch(new FooterPageletLoadedEvent($pagelet, $channelContext, $request));

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
}
