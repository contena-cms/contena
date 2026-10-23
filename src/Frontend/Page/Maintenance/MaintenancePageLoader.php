<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Maintenance;

use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoaderInterface;
use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Content\Cms\Exception\PageNotFoundException;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class MaintenancePageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ChannelCmsPageLoaderInterface $cmsPageLoader,
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws CmsException|PageNotFoundException
     */
    public function load(string $cmsErrorLayoutId, Request $request, ChannelContext $context): MaintenancePage
    {
        try {
            $page = $this->genericLoader->load($request, $context);
            $page = MaintenancePage::createFrom($page);

            $pages = $this->cmsPageLoader->load($request, new Criteria([$cmsErrorLayoutId]), $context)->getEntities();
        } catch (\Throwable) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore contena.domainException (Will be fixed with next major) */
                throw new PageNotFoundException($cmsErrorLayoutId);
            }
            throw CmsException::pageNotFound($cmsErrorLayoutId);
        }

        $cmsPage = $pages->first();
        if ($cmsPage === null) {
            if (!Feature::isActive('v6.8.0.0')) {
                /** @phpstan-ignore contena.domainException (Will be fixed with next major) */
                throw new PageNotFoundException($cmsErrorLayoutId);
            }
            throw CmsException::pageNotFound($cmsErrorLayoutId);
        }

        $page->setCmsPage($cmsPage);

        $this->eventDispatcher->dispatch(new MaintenancePageLoadedEvent($page, $context, $request));

        return $page;
    }
}
