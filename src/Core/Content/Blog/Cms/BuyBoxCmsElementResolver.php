<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\BuyBoxStruct;
use Contena\Core\Content\Blog\Channel\Detail\BlogConfiguratorLoader;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

class BuyBoxCmsElementResolver extends AbstractBlogDetailCmsElementResolver
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogReviewCollection> $repository
     */
    public function __construct(
        private readonly BlogConfiguratorLoader $configuratorLoader,
        private readonly EntityRepository $repository
    ) {
    }

    public function getType(): string
    {
        return 'buy-box';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $buyBox = new BuyBoxStruct();
        $slot->setData($buyBox);

        $blogConfig = $slot->getFieldConfig()->get('blog');
        if ($blogConfig === null) {
            return;
        }

        $blog = null;

        if ($blogConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $blog = $this->resolveEntityValue($resolverContext->getEntity(), $blogConfig->getStringValue());
        }

        if ($blogConfig->isStatic()) {
            $blog = $this->getSlotBlog($slot, $result, $blogConfig->getStringValue());
        }

        /** @var ChannelBlogEntity|null $blog */
        if ($blog !== null) {
            $buyBox->setBlog($blog);
            $buyBox->setBlogId($blog->getId());
            $buyBox->setConfiguratorSettings($this->configuratorLoader->load($blog, $resolverContext->getChannelContext()));
            $buyBox->setTotalReviews($this->getReviewsCount($blog, $resolverContext->getChannelContext()));
        }
    }

    private function getReviewsCount(ChannelBlogEntity $blog, ChannelContext $context): int
    {
        $reviewCriteria = $this->createReviewCriteria($context, $blog->getParentId() ?? $blog->getId());

        $aggregation = $this->repository->aggregate($reviewCriteria, $context->getContext())->get('review-count');

        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    private function createReviewCriteria(ChannelContext $context, string $blogId): Criteria
    {
        $reviewFilters = [];
        $criteria = new Criteria();

        $reviewFilters[] = new EqualsFilter('status', true);
        if ($context->getUser()) {
            $reviewFilters[] = new EqualsFilter('userId', $context->getUserId());
        }

        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new MultiFilter(MultiFilter::CONNECTION_OR, $reviewFilters),
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('blog.id', $blogId),
                    new EqualsFilter('blog.parentId', $blogId),
                ]),
            ])
        );

        $criteria->addAggregation(new CountAggregation('review-count', 'id'));

        return $criteria;
    }
}
