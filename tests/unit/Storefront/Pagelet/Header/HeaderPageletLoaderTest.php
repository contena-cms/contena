<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Pagelet\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Content\Category\Tree\TreeItem;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Contena\Core\System\Currency\SalesChannel\CurrencyRouteResponse;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Contena\Core\System\Language\SalesChannel\LanguageRouteResponse;
use Contena\Core\Test\Generator;
use Contena\Storefront\Pagelet\Header\HeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(HeaderPageletLoader::class)]
class HeaderPageletLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $eventDispatcher = static::createStub(EventDispatcherInterface::class);

        $salesChannelContext = Generator::generateSalesChannelContext();

        $currencyRoute = static::createStub(AbstractCurrencyRoute::class);
        $currencyRoute->method('load')->willReturn(new CurrencyRouteResponse(new CurrencyCollection([
            (new CurrencyEntity())->assign(['id' => $salesChannelContext->getCurrencyId()]),
        ])));

        $languageRoute = static::createStub(AbstractLanguageRoute::class);
        $languageRoute->method('load')->willReturn(new LanguageRouteResponse(new EntitySearchResult(
            LanguageDefinition::ENTITY_NAME,
            1,
            new LanguageCollection([
                (new LanguageEntity())->assign(['id' => $salesChannelContext->getLanguageId()]),
            ]),
            null,
            new Criteria(),
            $salesChannelContext->getContext(),
        )));

        $navigationLoader = static::createStub(NavigationLoaderInterface::class);
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $category1 = (new CategoryEntity())->assign(['id' => $categoryId1]);
        $category2 = (new CategoryEntity())->assign(['id' => $categoryId2]);
        $navigationCategoryId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();
        $navigationLoader->method('load')->willReturnMap(
            [
                [
                    $navigationCategoryId,
                    $salesChannelContext,
                    $navigationCategoryId,
                    $salesChannelContext->getSalesChannel()->getNavigationCategoryDepth(),
                    new Tree($category2, [new TreeItem($category1, []), new TreeItem($category2, [])]),
                ],
            ]
        );

        $headerPageletLoader = new HeaderPageletLoader($eventDispatcher, $currencyRoute, $languageRoute, $navigationLoader);
        $header = $headerPageletLoader->load(new Request(), $salesChannelContext);

        $navigation = $header->getNavigation();
        static::assertNotNull($navigation);
        $tree = $navigation->getTree();
        static::assertCount(2, $tree);
        static::assertSame($categoryId1, $tree[0]->getCategory()->getId());
        static::assertSame($categoryId2, $tree[1]->getCategory()->getId());
    }
}
