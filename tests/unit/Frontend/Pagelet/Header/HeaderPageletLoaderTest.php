<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Pagelet\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Content\Category\Tree\TreeItem;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\Channel\AbstractLanguageRoute;
use Contena\Core\System\Language\Channel\LanguageRouteResponse;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\Test\Generator;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(HeaderPageletLoader::class)]
class HeaderPageletLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $channelContext = Generator::generateChannelContext();

        $languageRoute = static::createStub(AbstractLanguageRoute::class);
        $languageRoute->method('load')->willReturn(new LanguageRouteResponse(new EntitySearchResult(
            1,
            new LanguageCollection([
                new LanguageEntity()->assign(['id' => $channelContext->getLanguageId()]),
            ]),
            null,
            new Criteria(),
            $channelContext->getContext(),
        )));

        $navigationLoader = static::createStub(NavigationLoaderInterface::class);
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $category1 = new CategoryEntity()->assign(['id' => $categoryId1]);
        $category2 = new CategoryEntity()->assign(['id' => $categoryId2]);
        $navigationCategoryId = $channelContext->getChannel()->getNavigationCategoryId();
        $navigationLoader->method('load')->willReturnMap([
            [
                $navigationCategoryId,
                $channelContext,
                $navigationCategoryId,
                $channelContext->getChannel()->getNavigationCategoryDepth(),
                new Tree($category2, [new TreeItem($category1, []), new TreeItem($category2, [])]),
            ],
        ]);

        $headerPageletLoader = new HeaderPageletLoader($eventDispatcher, $languageRoute, $navigationLoader);
        $header = $headerPageletLoader->load(new Request(), $channelContext);

        $navigation = $header->getNavigation();
        static::assertNotNull($navigation);
        $tree = $navigation->getTree();
        static::assertCount(2, $tree);
        static::assertSame($categoryId1, $tree[0]->getCategory()->getId());
        static::assertSame($categoryId2, $tree[1]->getCategory()->getId());
    }
}
