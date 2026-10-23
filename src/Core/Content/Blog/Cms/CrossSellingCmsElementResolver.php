<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\CrossSelling\AbstractBlogCrossSellingRoute;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\CrossSellingStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;

class CrossSellingCmsElementResolver extends AbstractBlogDetailCmsElementResolver
{
    final public const TYPE = 'cross-selling';

    /**
     * @internal
     */
    public function __construct(private readonly AbstractBlogCrossSellingRoute $crossSellingLoader)
    {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $context = $resolverContext->getChannelContext();
        $struct = new CrossSellingStruct();
        $slot->setData($struct);

        $blogConfig = $config->get('blog');

        if ($blogConfig === null || $blogConfig->getValue() === null) {
            return;
        }

        $blog = null;

        if ($blogConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $blog = $this->resolveEntityValue($resolverContext->getEntity(), $blogConfig->getStringValue());
        }

        if ($blogConfig->isStatic()) {
            $blog = $this->getSlotBlog($slot, $result, $blogConfig->getStringValue());
        }

        if (!$blog instanceof ChannelBlogEntity) {
            return;
        }

        $crossSellings = $this->crossSellingLoader->load($blog->getId(), new Request(), $context, new Criteria())->getResult();

        if ($crossSellings->count()) {
            $struct->setCrossSellings($crossSellings);
        }
    }
}
