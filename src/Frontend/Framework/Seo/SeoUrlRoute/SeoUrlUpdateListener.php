<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Seo\SeoUrlRoute;

use Contena\Core\Content\Category\CategoryEvents;
use Contena\Core\Content\Category\Event\CategoryIndexerEvent;
use Contena\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Contena\Core\Content\LandingPage\LandingPageEvents;
use Contena\Core\Content\Product\Events\ProductIndexerEvent;
use Contena\Core\Content\Product\ProductEvents;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * This listener updates the seo urls for the product, category and landing page routes when the corresponding entities are indexed.
 * It assumes that for every parent child relation the indexer will take care of fetching the child ids
 * and dispatching the event for the children as well.
 *
 * @internal
 */
#[Package('inventory')]
class SeoUrlUpdateListener implements EventSubscriberInterface
{
    final public const CATEGORY_SEO_URL_UPDATER = 'category.seo-url';
    final public const PRODUCT_SEO_URL_UPDATER = 'product.seo-url';
    final public const LANDING_PAGE_SEO_URL_UPDATER = 'landing_page.seo-url';

    /**
     * @internal
     */
    public function __construct(
        private readonly SeoUrlUpdater $seoUrlUpdater,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_INDEXER_EVENT => 'updateProductUrls',
            CategoryEvents::CATEGORY_INDEXER_EVENT => 'updateCategoryUrls',
            LandingPageEvents::LANDING_PAGE_INDEXER_EVENT => 'updateLandingPageUrls',
        ];
    }

    public function updateCategoryUrls(CategoryIndexerEvent $event): void
    {
        if (\in_array(self::CATEGORY_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->seoUrlUpdater->update(NavigationPageSeoUrlRoute::ROUTE_NAME, $event->getIds());
    }

    public function updateProductUrls(ProductIndexerEvent $event): void
    {
        if (\in_array(self::PRODUCT_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->seoUrlUpdater->update(ProductPageSeoUrlRoute::ROUTE_NAME, array_values($event->getIds()));
    }

    public function updateLandingPageUrls(LandingPageIndexerEvent $event): void
    {
        if (\in_array(self::LANDING_PAGE_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->seoUrlUpdater->update(LandingPageSeoUrlRoute::ROUTE_NAME, array_values($event->getIds()));
    }
}
