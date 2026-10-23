<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Cms\CategoryNavigationCmsElementResolver;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Content\Category\Tree\TreeItem;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CategoryNavigationCmsElementResolver::class)]
class CategoryNavigationCmsElementResolverTest extends TestCase
{
    public function testEnrich(): void
    {
        $channelContext = Generator::generateChannelContext();
        $channel = $channelContext->getChannel();

        $navigationLoader = static::createStub(NavigationLoaderInterface::class);
        $categoryId1 = $channel->getNavigationCategoryId();
        $categoryId2 = Uuid::randomHex();
        $category1 = (new CategoryEntity())->assign(['id' => $categoryId1]);
        $category2 = (new CategoryEntity())->assign(['id' => $categoryId2]);
        $navigationLoader->method('load')->willReturnMap(
            [
                [
                    $categoryId2,
                    $channelContext,
                    $channel->getNavigationCategoryId(),
                    $channel->getNavigationCategoryDepth(),
                    new Tree($category2, [new TreeItem($category1, []), new TreeItem($category2, [])]),
                ],
            ]
        );

        $slot = new CmsSlotEntity();

        $resolverContext = new ResolverContext(
            $channelContext,
            new Request(['navigationId' => $categoryId2])
        );

        (new CategoryNavigationCmsElementResolver($navigationLoader))->enrich(
            $slot,
            $resolverContext,
            new ElementDataCollection()
        );

        $navigation = $slot->getData();
        static::assertInstanceOf(Tree::class, $navigation);

        static::assertSame($categoryId2, $navigation->getActive()?->getId());

        $tree = $navigation->getTree();
        static::assertCount(2, $tree);
        static::assertSame($categoryId1, $tree[0]->getCategory()->getId());
        static::assertSame($categoryId2, $tree[1]->getCategory()->getId());
    }
}
