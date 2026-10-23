<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogBoxStruct;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;

class BlogBoxCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getType(): string
    {
        return 'blog-box';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $blogConfig = $slot->getFieldConfig()->get('blog');
        if ($blogConfig === null || $blogConfig->isMapped() || $blogConfig->getValue() === null) {
            return null;
        }

        $criteria = new Criteria([$blogConfig->getStringValue()]);
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('options.group');

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('blog_' . $slot->getUniqueIdentifier(), BlogDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $blogBox = new BlogBoxStruct();
        $slot->setData($blogBox);

        $blogConfig = $slot->getFieldConfig()->get('blog');
        if ($blogConfig === null || $blogConfig->getValue() === null) {
            return;
        }

        if ($resolverContext instanceof EntityResolverContext && $blogConfig->isMapped()) {
            /** @var ChannelBlogEntity $blog */
            $blog = $this->resolveEntityValue($resolverContext->getEntity(), $blogConfig->getStringValue());

            $blogBox->setBlog($blog);
            $blogBox->setBlogId($blog->getId());
        }

        if ($blogConfig->isStatic()) {
            $this->resolveBlogFromRemote($slot, $blogBox, $result, $blogConfig->getStringValue(), $resolverContext->getChannelContext());
        }
    }

    private function resolveBlogFromRemote(
        CmsSlotEntity $slot,
        BlogBoxStruct $blogBox,
        ElementDataCollection $result,
        string $blogId,
        ChannelContext $channelContext
    ): void {
        $blog = $result->get('blog_' . $slot->getUniqueIdentifier())?->getEntities()->get($blogId);
        if (!$blog instanceof ChannelBlogEntity) {
            return;
        }

        if ($blog->getIsCloseout()
            && $blog->getStock() <= 0
            && $this->systemConfigService->getBool('core.listing.hideCloseoutBlogsWhenOutOfStock', $channelContext->getChannelId())
        ) {
            return;
        }

        $blogBox->setBlog($blog);
        $blogBox->setBlogId($blog->getId());
    }
}
