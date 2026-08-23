<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo\SeoUrlRoute;

use Contena\Core\Content\Blog\BlogEvents;
use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Content\Category\CategoryEvents;
use Contena\Core\Content\Category\Event\CategoryIndexerEvent;
use Contena\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Contena\Core\Content\LandingPage\LandingPageEvents;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Updates SEO URLs for blogs, categories and landing pages when the corresponding entities are indexed.
 *
 * @internal
 */
class SeoUrlUpdateListener implements EventSubscriberInterface
{
    final public const CATEGORY_SEO_URL_UPDATER = 'category.seo-url';
    final public const BLOG_SEO_URL_UPDATER = 'blog.seo-url';
    final public const LANDING_PAGE_SEO_URL_UPDATER = 'landing_page.seo-url';

    /**
     * @internal
     */
    public function __construct(private readonly SeoUrlUpdater $seoUrlUpdater)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BlogEvents::BLOG_INDEXER_EVENT => 'updateBlogUrls',
            CategoryEvents::CATEGORY_INDEXER_EVENT => 'updateCategoryUrls',
            LandingPageEvents::LANDING_PAGE_INDEXER_EVENT => 'updateLandingPageUrls',
        ];
    }

    public function updateCategoryUrls(CategoryIndexerEvent $event): void
    {
        if (\in_array(self::CATEGORY_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->seoUrlUpdater->update(NavigationPageSeoUrlRoute::ROUTE_NAME, $event->getIds(), $event->getContext());
    }

    public function updateBlogUrls(BlogIndexerEvent $event): void
    {
        if (\in_array(self::BLOG_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->seoUrlUpdater->update(BlogPageSeoUrlRoute::ROUTE_NAME, array_values($event->getIds()), $event->getContext());
    }

    public function updateLandingPageUrls(LandingPageIndexerEvent $event): void
    {
        if (\in_array(self::LANDING_PAGE_SEO_URL_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->seoUrlUpdater->update(LandingPageSeoUrlRoute::ROUTE_NAME, array_values($event->getIds()), $event->getContext());
    }
}
