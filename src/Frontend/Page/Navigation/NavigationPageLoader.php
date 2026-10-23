<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Content\Category\Channel\AbstractCategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a channel-api route to get or put data.
 */
class NavigationPageLoader implements NavigationPageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCategoryRoute $cmsPageRoute,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder
    ) {
    }

    public function load(Request $request, ChannelContext $context): NavigationPage
    {
        $page = $this->genericLoader->load($request, $context);
        $page = NavigationPage::createFrom($page);

        $navigationId = $request->attributes->get('navigationId', $context->getChannel()->getNavigationCategoryId());

        $category = $this->cmsPageRoute
            ->load($navigationId, $request, $context)
            ->getCategory();

        if (!$category->getActive()) {
            throw CategoryException::categoryNotFound($category->getId());
        }

        $this->loadMetaData($category, $page, $context->getChannel());
        $page->setNavigationId($category->getId());
        $page->setCategory($category);

        if (Feature::isActive('BREADCRUMB_REWORK')) {
            $page->setBreadcrumb($this->breadcrumbBuilder->getCategoryBreadcrumbUrls($category, $context->getContext(), $context->getChannel()));
        }

        if ($category->getCmsPage()) {
            $page->setCmsPage($category->getCmsPage());
        }

        if ($page->getMetaInformation()) {
            $canonical = ($navigationId === $context->getChannel()->getNavigationCategoryId())
                ? $this->seoUrlReplacer->generate('frontend.home.page')
                : $this->seoUrlReplacer->generate(NavigationPageSeoUrlRoute::ROUTE_NAME, ['navigationId' => $navigationId]);

            if ($request->query->has('p') && $request->query->getInt('p') > 1) {
                $canonical .= '?p=' . $request->query->get('p');
            }

            $page->getMetaInformation()->setCanonical($canonical);
        }

        $this->eventDispatcher->dispatch(
            new NavigationPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadMetaData(CategoryEntity $category, NavigationPage $page, ChannelEntity $channel): void
    {
        $metaInformation = $page->getMetaInformation();

        if ($metaInformation === null) {
            return;
        }

        $isHome = $channel->getNavigationCategoryId() === $category->getId();

        $metaDescription = $isHome && $channel->getTranslation('homeMetaDescription')
            ? $channel->getTranslation('homeMetaDescription')
            : $category->getTranslation('metaDescription')
            ?? $category->getTranslation('description');
        $metaInformation->setMetaDescription((string) $metaDescription);

        $metaTitle = $isHome && $channel->getTranslation('homeMetaTitle')
            ? $channel->getTranslation('homeMetaTitle')
            : $category->getTranslation('metaTitle')
            ?? $category->getTranslation('name');
        $metaInformation->setMetaTitle((string) $metaTitle);

        $keywords = $isHome && $channel->getTranslation('homeKeywords')
            ? $channel->getTranslation('homeKeywords')
            : $category->getTranslation('keywords');
        $metaInformation->setMetaKeywords((string) $keywords);
    }
}
