<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Newsletter\Subscribe;

use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class NewsletterSubscribePageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $channelContext): NewsletterSubscribePage
    {
        $page = $this->genericLoader->load($request, $channelContext);
        $page = NewsletterSubscribePage::createFrom($page);

        $this->eventDispatcher->dispatch(
            new NewsletterSubscribePageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }
}
