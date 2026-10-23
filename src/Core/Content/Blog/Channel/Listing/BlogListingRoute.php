<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Extension\BlogListingCriteriaExtension;
use Contena\Core\Content\Blog\Extension\BlogListingRouteExtension;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Content\ContentStream\Exception\EmptyContentStreamException;
use Contena\Core\Content\ContentStream\Exception\NoFilterException;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BlogListingRoute extends AbstractBlogListingRoute
{

    public function getDecorated(): AbstractBlogListingRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $categoryId): string
    {
        return 'blog-listing-' . $categoryId;
    }

    #[Route(
        path: '/store-api/blog-listing/{categoryId}',
        name: 'store-api.blog.listing',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $categoryId, Request $request, ChannelContext $context, Criteria $criteria): BlogListingRouteResponse
    {
        return $this->extensions->publish(
            name: BlogListingRouteExtension::NAME,
            extension: new BlogListingRouteExtension($categoryId, $request, $context, $criteria),
            function: fn (string $categoryId, Request $request, ChannelContext $context, Criteria $criteria): BlogListingRouteResponse => $this->_load($categoryId, $context, $criteria),
        );
    }

    private function _load(string $categoryId, ChannelContext $context, Criteria $criteria): BlogListingRouteResponse
    {
        $this->cacheTagCollector->addTag(self::buildName($categoryId));

        $criteria->addFilter(
            new BlogAvailableFilter($context->getChannelId(), BlogVisibilityDefinition::VISIBILITY_ALL)
        );
        $criteria->setTitle('blog-listing-route::loading');

        $categoryCriteria = new Criteria([$categoryId]);
        $categoryCriteria->setTitle('blog-listing-route::category-loading');
        $categoryCriteria->addFields(['blogAssignmentType', 'contentStreamId']);
        $categoryCriteria->setLimit(1);

        /** @var ?PartialEntity */
        $category = $this->categoryRepository->search($categoryCriteria, $context->getContext())->getEntities()->first();
        if (!$category) {
            throw BlogException::categoryNotFound($categoryId);
        }

        $criteria = $this->extensions->publish(
            name: BlogListingCriteriaExtension::NAME,
            extension: new BlogListingCriteriaExtension($criteria, $context, $categoryId),
            function: function ($criteria, $context, $categoryId) use ($category): Criteria {
                $this->extendCriteria($context, $criteria, $category);

                return $criteria;
            }
        );

        $entities = $this->listingLoader->load($criteria, $context);

        $result = BlogListingResult::createFrom($entities);
        $result->addState(...$entities->getStates());

        $result->setStreamId($category->get('contentStreamId'));

        return new BlogListingRouteResponse($result);
    }

    private function extendCriteria(ChannelContext $channelContext, Criteria $criteria, PartialEntity $category): void
    {
        $blogAssignmentType = $category->get('blogAssignmentType');
        $contentStreamId = $category->get('contentStreamId');

        if ($blogAssignmentType === CategoryDefinition::BLOG_ASSIGNMENT_TYPE_CONTENT_STREAM && \is_string($contentStreamId) && $contentStreamId !== '') {
            $this->cacheTagCollector->addTag(
                EntityCacheKeyGenerator::buildStreamTag($contentStreamId)
            );

            $contentStreamBuilder = $this->contentStreamBuilder;

            try {
                if ($contentStreamBuilder instanceof AbstractContentStreamBuilder) {
                    $contentStreamBuilder->enrichCriteria($criteria, $contentStreamId, $channelContext->getContext());
                } else {
                    $criteria->addFilter(...$contentStreamBuilder->buildFilters($contentStreamId, $channelContext->getContext()));
                }
            } catch (EmptyContentStreamException) {
                // An empty group selects all blogs, as in the blog export
            } catch (NoFilterException $exception) {
                $this->logger->warning(
                    'Blog stream configured for category listing has no usable filters.',
                    [
                        'contentStreamId' => $contentStreamId,
                        'exception' => $exception,
                    ]
                );

                $criteria->addFilter(new EqualsFilter('blog.id', null));
            }

            return;
        }

        $criteria->addFilter(
            new EqualsFilter('blog.categoriesRo.id', $category->getId())
        );
    }
}
