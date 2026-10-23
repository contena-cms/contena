<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewsWidgetLoadedHook;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogDescriptionReviewsStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;

class BlogDescriptionReviewsCmsElementResolver extends AbstractBlogDetailCmsElementResolver
{
    final public const TYPE = 'blog-description-reviews';


    public function getType(): string
    {
        return self::TYPE;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new BlogDescriptionReviewsStruct();
        $slot->setData($data);

        $blogConfig = $slot->getFieldConfig()->get('blog');
        if ($blogConfig === null) {
            return;
        }

        $request = $resolverContext->getRequest();
        $ratingSuccess = (bool) RequestParamHelper::get($request, 'success', false);
        $data->setRatingSuccess($ratingSuccess);

        $blog = null;

        if ($blogConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $blog = $this->resolveEntityValue($resolverContext->getEntity(), $blogConfig->getStringValue());
        }

        if ($blogConfig->isStatic()) {
            $blog = $this->getSlotBlog($slot, $result, $blogConfig->getStringValue());
        }

        if (!$blog instanceof ChannelBlogEntity) {
            // blog can not be resolved, so we do not enrich the slot
            return;
        }

        $data->setBlog($blog);

        if ($this->systemConfigService->getBool('core.listing.showReview', $resolverContext->getChannelContext()->getChannelId())) {
            $reviews = $this->blogReviewLoader->load($request, $resolverContext->getChannelContext(), $blog->getId(), $blog->getParentId());

            $this->scriptExecutor->execute(new BlogReviewsWidgetLoadedHook($reviews, $resolverContext->getChannelContext()));

            $data->setReviews($reviews);
        }
    }
}
