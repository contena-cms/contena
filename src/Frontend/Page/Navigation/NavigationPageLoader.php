<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation;

use Contena\Core\Content\Blog\Channel\Listing\AbstractBlogListingRoute;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Content\Category\Channel\AbstractCategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageLoader implements NavigationPageLoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCategoryRoute $categoryRoute,
        private readonly AbstractBlogListingRoute $blogListingRoute,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder
    ) {
    }

    public function load(Request $request, ChannelContext $context): NavigationPage
    {
        $page = NavigationPage::createFrom($this->genericLoader->load($request, $context));
        $navigationId = $request->attributes->get('navigationId', $context->getChannel()->getNavigationCategoryId());

        $category = $this->categoryRoute->load($navigationId, $request, $context)->getCategory();
        if (!$category->getActive()) {
            throw CategoryException::categoryNotFound($category->getId());
        }

        $this->loadMetaData($category, $page, $context->getChannel());
        $page->setNavigationId($category->getId());
        $page->setCategory($category);
        $page->setBreadcrumb($this->breadcrumbBuilder->getCategoryBreadcrumbUrls($category, $context->getContext(), $context->getChannel()));

        $criteria = new Criteria();
        $criteria->setTitle('navigation-page');
        $page->setListing(
            $this->blogListingRoute->load($category->getId(), $request, $context, $criteria)->getResult()
        );

        if ($page->getMetaInformation()) {
            $canonical = ($navigationId === $context->getChannel()->getNavigationCategoryId())
                ? $this->seoUrlReplacer->generate('frontend.home.page')
                : $this->seoUrlReplacer->generate(NavigationPageSeoUrlRoute::ROUTE_NAME, ['navigationId' => $navigationId]);
            if ($request->query->has('p') && $request->query->getInt('p') > 1) {
                $canonical .= '?p=' . $request->query->get('p');
            }
            $page->getMetaInformation()->setCanonical($canonical);
        }

        $this->eventDispatcher->dispatch(new NavigationPageLoadedEvent($page, $context, $request));

        return $page;
    }

    private function loadMetaData(CategoryEntity $category, NavigationPage $page, ChannelEntity $channel): void
    {
        $metaInformation = $page->getMetaInformation();
        if ($metaInformation === null) {
            return;
        }

        $isHome = $channel->getNavigationCategoryId() === $category->getId();
        $metaInformation->setMetaDescription((string) (($isHome ? $channel->getTranslation('homeMetaDescription') : null) ?: $category->getTranslation('metaDescription') ?? $category->getTranslation('description')));
        $metaInformation->setMetaTitle((string) (($isHome ? $channel->getTranslation('homeMetaTitle') : null) ?: $category->getTranslation('metaTitle') ?? $category->getTranslation('name')));
        $metaInformation->setMetaKeywords((string) (($isHome ? $channel->getTranslation('homeKeywords') : null) ?: $category->getTranslation('keywords')));
    }
}
