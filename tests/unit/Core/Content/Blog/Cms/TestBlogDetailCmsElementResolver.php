<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Cms\AbstractBlogDetailCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;

/**
 * @internal
 */
class TestBlogDetailCmsElementResolver extends AbstractBlogDetailCmsElementResolver
{
    public function getType(): string
    {
        return 'test';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        // nth
    }

    public function runGetSlotBlog(CmsSlotEntity $slot, ElementDataCollection $result, string $blogId): ?ChannelBlogEntity
    {
        return $this->getSlotBlog($slot, $result, $blogId);
    }
}
