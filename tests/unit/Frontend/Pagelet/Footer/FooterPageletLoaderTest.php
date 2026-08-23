<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Pagelet\Footer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Content\Category\Tree\TreeItem;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FooterPageletLoader::class)]
class FooterPageletLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $serviceMenuId = Uuid::randomHex();
        $channelContext = Generator::generateChannelContext();
        $channelContext->getChannel()->setServiceCategoryId($serviceMenuId);

        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $navigationLoader = static::createStub(NavigationLoaderInterface::class);

        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $category1 = new CategoryEntity()->assign(['id' => $categoryId1]);
        $category2 = new CategoryEntity()->assign(['id' => $categoryId2]);
        $navigationLoader->method('load')->willReturnMap([
            [
                $serviceMenuId,
                $channelContext,
                $serviceMenuId,
                1,
                new Tree($category2, [new TreeItem($category1, []), new TreeItem($category2, [])]),
            ],
        ]);

        $footerPageletLoader = new FooterPageletLoader($eventDispatcher, $navigationLoader);
        $footer = $footerPageletLoader->load(new Request(), $channelContext);

        $serviceMenu = $footer->getServiceMenu();
        static::assertCount(2, $serviceMenu);
        static::assertSame($category1, $serviceMenu->get($categoryId1));
        static::assertSame($category2, $serviceMenu->get($categoryId2));
    }
}
