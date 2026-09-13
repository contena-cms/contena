<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Channel;

use Contena\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Channel\CategoryRouteResponse;
use Contena\Core\Content\Category\Channel\ChannelCategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CategoryRoute::class)]
class CategoryRouteTest extends TestCase
{
    public function testBreadcrumbIsAddedToTheCategoryAndTagsTheWholePath(): void
    {
        $category = $this->buildPageCategory();
        $homeId = Uuid::randomHex();
        $breadcrumb = new BreadcrumbCollection([
            new Breadcrumb('Home', $homeId),
            new Breadcrumb('Articles', $category->getId()),
        ]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->exactly(2))
            ->method('addTag')
            ->willReturnCallback(function (string ...$tags) use ($category, $homeId): void {
                static::assertContains(
                    $tags,
                    [
                        [CategoryRoute::buildName($category->getId())],
                        [CategoryRoute::buildName($homeId), CategoryRoute::buildName($category->getId())],
                    ]
                );
            });

        $response = $this->loadCategory($category, new Request(), $breadcrumbBuilder, $cacheTagCollector);

        $loadedCategory = $response->getCategory();
        static::assertInstanceOf(ChannelCategoryEntity::class, $loadedCategory);
        static::assertSame($breadcrumb, $loadedCategory->getSeoBreadcrumb());
    }

    public function testHomeRouteIsTaggedWithNavigationCategoryId(): void
    {
        $category = $this->buildPageCategory();
        $category->setId(Generator::NAVIGATION_CATEGORY);

        $request = new Request([], [], [CategoryRoute::SKIP_BREADCRUMB => true]);
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(CategoryRoute::buildName($category->getId()));

        $this->loadCategory(
            $category,
            $request,
            static::createStub(CategoryBreadcrumbBuilder::class),
            $cacheTagCollector,
            CategoryRoute::HOME,
        );

        static::assertSame($category->getId(), $request->attributes->get('navigationId'));
    }

    public function testLinkCategoryCarriesItsBreadcrumb(): void
    {
        $category = $this->buildPageCategory();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('External', $category->getId())]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $response = $this->loadCategory($category, new Request(), $breadcrumbBuilder);

        $loadedCategory = $response->getCategory();
        static::assertInstanceOf(ChannelCategoryEntity::class, $loadedCategory);
        static::assertSame($breadcrumb, $loadedCategory->getSeoBreadcrumb());
    }

    #[DataProvider('skipBreadcrumbRequestProvider')]
    public function testBreadcrumbIsNotLoadedWhenSkipped(Request $request): void
    {
        $category = $this->buildPageCategory();
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $response = $this->loadCategory($category, $request, $breadcrumbBuilder);

        $loadedCategory = $response->getCategory();
        static::assertInstanceOf(ChannelCategoryEntity::class, $loadedCategory);
        static::assertNull($loadedCategory->getSeoBreadcrumb());
    }

    public static function skipBreadcrumbRequestProvider(): \Generator
    {
        yield 'query parameter' => [new Request([CategoryRoute::SKIP_BREADCRUMB => '1'])];
        yield 'request body' => [new Request([], [CategoryRoute::SKIP_BREADCRUMB => true])];
        yield 'request attribute' => [new Request([], [], [CategoryRoute::SKIP_BREADCRUMB => true])];
    }

    public function testBreadcrumbRequestAttributeOverrulesTheClientParameter(): void
    {
        $category = $this->buildPageCategory();
        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Articles', $category->getId())]);
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $request = new Request(
            [CategoryRoute::SKIP_BREADCRUMB => '1'],
            [],
            [CategoryRoute::SKIP_BREADCRUMB => false]
        );

        $loadedCategory = $this->loadCategory($category, $request, $breadcrumbBuilder)->getCategory();
        static::assertInstanceOf(ChannelCategoryEntity::class, $loadedCategory);
        static::assertSame($breadcrumb, $loadedCategory->getSeoBreadcrumb());
    }

    public function testMalformedSkipBreadcrumbParameterIsIgnored(): void
    {
        $category = $this->buildPageCategory();
        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Articles', $category->getId())]);
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $request = new Request([CategoryRoute::SKIP_BREADCRUMB => 'not-a-bool']);

        $loadedCategory = $this->loadCategory($category, $request, $breadcrumbBuilder)->getCategory();
        static::assertInstanceOf(ChannelCategoryEntity::class, $loadedCategory);
        static::assertSame($breadcrumb, $loadedCategory->getSeoBreadcrumb());
    }

    public function testBreadcrumbIsNotBuiltForAFolderCategory(): void
    {
        $category = $this->buildPageCategory();
        $category->setType(CategoryDefinition::TYPE_FOLDER);
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $this->expectExceptionObject(CategoryException::categoryNotFound($category->getId()));

        $this->loadCategory($category, new Request(), $breadcrumbBuilder);
    }

    private function loadCategory(
        ChannelCategoryEntity $category,
        Request $request,
        CategoryBreadcrumbBuilder $breadcrumbBuilder,
        ?CacheTagCollector $cacheTagCollector = null,
        ?string $navigationId = null,
    ): CategoryRouteResponse {
        $context = Generator::generateChannelContext();
        $repository = static::createStub(ChannelRepository::class);
        $repository->method('search')->willReturn($this->categorySearchResult($category, $context));

        return new CategoryRoute(
            $repository,
            $cacheTagCollector ?? static::createStub(CacheTagCollector::class),
            $breadcrumbBuilder,
        )->load($navigationId ?? $category->getId(), $request, $context);
    }

    /**
     * @return EntitySearchResult<CategoryCollection>
     */
    private function categorySearchResult(ChannelCategoryEntity $category, ChannelContext $context): EntitySearchResult
    {
        return new EntitySearchResult(
            1,
            new CategoryCollection([$category]),
            null,
            new Criteria(),
            $context->getContext(),
        );
    }

    private function buildPageCategory(): ChannelCategoryEntity
    {
        $category = new ChannelCategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_PAGE);
        $category->setActive(true);

        return $category;
    }
}
