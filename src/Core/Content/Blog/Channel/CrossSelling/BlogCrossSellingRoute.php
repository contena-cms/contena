<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\CrossSelling;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Events\BlogCrossSellingsLoadedEvent;
use Contena\Core\Content\Blog\Extension\BlogCrossSellingRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BlogCrossSellingRoute extends AbstractBlogCrossSellingRoute
{

    public function getDecorated(): AbstractBlogCrossSellingRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public static function buildName(string $id): string
    {
        return EntityCacheKeyGenerator::buildBlogTag($id);
    }

    #[Route(
        path: '/store-api/blog/{blogId}/cross-selling',
        name: 'store-api.blog.cross-selling',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogCrossSellingRouteResponse
    {
        return $this->extensions->publish(
            name: BlogCrossSellingRouteExtension::NAME,
            extension: new BlogCrossSellingRouteExtension($blogId, $request, $context, $criteria),
            function: fn (string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogCrossSellingRouteResponse => $this->_load($blogId, $context, $criteria),
        );
    }

    private function _load(string $blogId, ChannelContext $context, Criteria $criteria): BlogCrossSellingRouteResponse
    {
        $crossSellings = $this->loadCrossSellings($blogId, $context);

        $rootBlogId = $crossSellings->count() > 0
            ? $this->fetchRootBlogId($blogId, $context)
            : $blogId;

        $elements = new CrossSellingElementCollection();

        foreach ($crossSellings as $crossSelling) {
            // CrossSellingElement is typed against BlogCollection, a field selection would load PartialEntity instances
            $clone = clone $criteria;
            $clone->resetFields();

            if ($this->useBlogStream($crossSelling)) {
                $element = $this->loadByStream($crossSelling, $rootBlogId, $context, $clone);
            } else {
                $element = $this->loadByIds($crossSelling, $context, $clone);
            }

            $elements->add($element);
        }

        $this->eventDispatcher->dispatch(new BlogCrossSellingsLoadedEvent($elements, $context));

        $tags = [self::buildName($blogId)];

        if (Feature::isActive('CACHE_REWORK')) {
            $tags = array_merge($tags, $this->getCrossSellingTags($elements));
        }

        $this->cacheTagCollector->addTag(...$tags);

        return new BlogCrossSellingRouteResponse($elements);
    }

    /**
     * @return list<string>
     */
    private function getCrossSellingTags(CrossSellingElementCollection $elements): array
    {
        $tags = [];

        foreach ($elements as $element) {
            foreach ($element->getBlogs() as $blog) {
                $tags[] = EntityCacheKeyGenerator::buildBlogTag($blog->getId());

                if ($blog->getParentId() !== null) {
                    $tags[] = EntityCacheKeyGenerator::buildBlogTag($blog->getParentId());
                }
            }
        }

        return array_values(array_unique(array_filter($tags)));
    }




    /**
     * A parent without any available variant would link to a blog detail page that is not found.
     */
    private function removeParentsWithoutAvailableVariant(BlogCollection $blogs, ChannelContext $context): void
    {
        $parentIds = $blogs
            ->filter(static fn (BlogEntity $blog): bool => $blog->getParentId() === null && $blog->getChildCount() > 0)
            ->getIds();

        if ($parentIds === []) {
            return;
        }

        $criteria = new Criteria();
        $criteria->setTitle('cross-selling::parents-with-available-variant');
        $criteria->addFilter(new EqualsAnyFilter('blog.parentId', array_values($parentIds)));
        $criteria->addAggregation(new TermsAggregation('parentIds', 'blog.parentId'));

        $criteria = $this->handleAvailableStock($criteria, $context);

        $aggregation = $this->blogRepository->aggregate($criteria, $context)->get('parentIds');
        $availableParentIds = $aggregation instanceof TermsResult ? $aggregation->getKeys() : [];

        foreach (array_diff($parentIds, $availableParentIds) as $parentId) {
            $blogs->remove($parentId);
        }
    }

    /**
     * Excludes the currently viewed blog from its own cross-selling. For variants, the complete variant
     * family is excluded: variant grouping and main variant resolution in {@see BlogListingLoader} would
     * otherwise resolve a sibling variant back to the currently viewed blog or to its parent.
     */
    private function createBlogExclusionFilter(string $rootBlogId): Filter
    {
        return new NotFilter(NotFilter::CONNECTION_OR, [
            new EqualsFilter('blog.id', $rootBlogId),
            new EqualsFilter('blog.parentId', $rootBlogId),
        ]);
    }

    /**
     * Cross-sellings are inherited, so the currently viewed blog is not necessarily the one the
     * cross-selling is assigned to. Resolve the root of the variant family the blog belongs to.
     */
    private function fetchRootBlogId(string $blogId, ChannelContext $context): string
    {
        $parentId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(parent_id)) FROM blog WHERE id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromHexToBytes($blogId),
                'versionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ]
        );

        return \is_string($parentId) ? $parentId : $blogId;
    }

    private function handleAvailableStock(Criteria $criteria, ChannelContext $context): Criteria
    {
        $channelId = $context->getChannelId();
        $hide = $this->systemConfigService->get('core.listing.hideCloseoutBlogsWhenOutOfStock', $channelId);

        if (!$hide) {
            return $criteria;
        }

        $closeoutFilter = $this->blogCloseoutFilterFactory->create($context);
        $criteria->addFilter($closeoutFilter);

        return $criteria;
    }

}
