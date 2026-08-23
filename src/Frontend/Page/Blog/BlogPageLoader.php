<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog;

use Contena\Core\Content\Blog\Channel\Detail\AbstractBlogDetailRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a Channel API route.
 */
class BlogPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBlogDetailRoute $blogDetailRoute,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
    ) {
    }

    public function load(Request $request, ChannelContext $context): BlogPage
    {
        $blogId = $request->attributes->get('blogId');
        if (!\is_string($blogId) || $blogId === '') {
            throw RoutingException::missingRequestParameter('blogId', '/blogId');
        }

        $criteria = new Criteria()
            ->addAssociation('mainCategories.category')
            ->addAssociation('media.media')
            ->addAssociation('cover.media')
            ->addAssociation('openGraphMedia');
        $criteria->getAssociation('media')->addSorting(new FieldSorting('position'));

        $this->eventDispatcher->dispatch(new BlogPageCriteriaEvent($blogId, $criteria, $context));

        $blog = $this->blogDetailRoute->load($blogId, $request, $context, $criteria)->getBlog();
        $page = BlogPage::createFrom($this->genericLoader->load($request, $context));
        $page->setBlog($blog);
        $page->setNavigationId($blog->getSeoCategory()?->getId());

        if ($category = $blog->getSeoCategory()) {
            $request->request->set('navigationId', $category->getId());
            $page->setBreadcrumb($this->breadcrumbBuilder->getCategoryBreadcrumbUrls($category, $context->getContext(), $context->getChannel()));
        }

        $this->loadMetaData($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setCanonical(
                $this->seoUrlReplacer->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId])
            );
        }

        $this->eventDispatcher->dispatch(new BlogPageLoadedEvent($page, $context, $request));

        return $page;
    }

    private function loadMetaData(BlogPage $page): void
    {
        $metaInformation = $page->getMetaInformation();
        if ($metaInformation === null) {
            return;
        }

        $blog = $page->getBlog();
        $metaInformation->setMetaDescription((string) ($blog->getTranslation('metaDescription') ?? $blog->getTranslation('descriptionTeaser') ?? $blog->getTranslation('description')));
        $metaInformation->setMetaKeywords((string) $blog->getTranslation('keywords'));
        $metaInformation->setMetaTitle((string) ($blog->getTranslation('metaTitle') ?: $blog->getTranslation('name')));
    }
}
