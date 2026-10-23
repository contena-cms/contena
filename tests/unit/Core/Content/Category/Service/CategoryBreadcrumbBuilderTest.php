<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Contena\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(CategoryBreadcrumbBuilder::class)]
class CategoryBreadcrumbBuilderTest extends TestCase
{
    protected ChannelContext $channelContext;

    private EntityRouteResolver $entityRouteResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channelContext = $this->getChannelContext();
        $entityRouteResolver = static::createStub(EntityRouteResolver::class);
        $entityRouteResolver->method('getRouteNameForEntityName')->willReturn('frontend.navigation.page');
        $this->entityRouteResolver = $entityRouteResolver;
    }

    public function testGetBlogSeoCategoryShouldReturnMainCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], [$categoryEntity]),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $blog = $this->getBlogEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext);

        static::assertNotNull($categoryEntity);
    }

    public function testGetBlogSeoCategoryMissingCategoryIdsAndStreamIds(): void
    {
        $categoryIds = [];
        $streamIds = null;

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId('');
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], [$categoryEntity]),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $blog = $this->getBlogEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext);

        static::assertNull($categoryEntity);
    }

    public function testGetBlogSeoCategoryHasCategoryIdsAndStreamIds(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];
        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([], []),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $blog = $this->getBlogEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext);

        static::assertNull($categoryEntity);
    }

    public function testGetBlogSeoCategoryShouldReturnBlogCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], []),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $blog = $this->getBlogEntity($streamIds, $categoryIds);
        $categoryEntity = $categoryBreadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext);

        static::assertNotNull($categoryEntity);
    }

    public function testGetBlogSeoCategoryShouldReturnBlogStreamCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];
        $streamIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], []),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $blog = $this->getBlogEntity($streamIds, []);
        $categoryEntity = $categoryBreadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext);

        static::assertNotNull($categoryEntity);
    }

    public function testGetBlogSeoCategoryShouldPreferDeepestVisibleActiveCategory(): void
    {
        $categoryIds = [Uuid::randomHex()];

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryIds[0]);
        $categoryEntity->setName('category-name-1');

        $categoryRepositoryMock = $this->createMock(EntityRepository::class);
        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $categoryRepositoryMock,
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $blog = $this->getBlogEntity([], $categoryIds);

        $context = $this->channelContext->getContext();
        $categoryRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) use ($categoryEntity, $context): EntitySearchResult {
                $sortings = $criteria->getSorting();

                static::assertCount(2, $sortings);
                static::assertSame('visible', $sortings[0]->getField());
                static::assertSame(FieldSorting::DESCENDING, $sortings[0]->getDirection());
                static::assertSame('level', $sortings[1]->getField());
                static::assertSame(FieldSorting::DESCENDING, $sortings[1]->getDirection());

                static::assertContains('active', $criteria->getFilterFields());
                static::assertNotContains('visible', $criteria->getFilterFields());

                return new EntitySearchResult('category', 1, new CategoryCollection([$categoryEntity]), null, $criteria, $context);
            });

        $categoryBreadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext);
    }

    public function testGetBlogSeoCategoryShouldReturnMainCategoryHiddenInNavigation(): void
    {
        $categoryId = Uuid::randomHex();

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryId);
        $categoryEntity->setName('hidden-main-category');
        $categoryEntity->setActive(true);
        $categoryEntity->setVisible(false);
        $categoryEntity->setPath('|navigationCategoryId|');

        $mainCategory = new MainCategoryEntity();
        $mainCategory->setId(Uuid::randomHex());
        $mainCategory->setChannelId($this->channelContext->getChannelId());
        $mainCategory->setCategory($categoryEntity);

        $blog = $this->getBlogEntity([], [$categoryId]);
        $blog->setMainCategories(new MainCategoryCollection([$mainCategory]));

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([], []),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        static::assertSame($categoryEntity, $categoryBreadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext));
    }

    public function testGetBlogCategoryByReferrerShouldReturnReferrerCategoryHiddenInNavigation(): void
    {
        $categoryId = Uuid::randomHex();

        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($categoryId);
        $categoryEntity->setName('hidden-referrer-category');
        $categoryEntity->setActive(true);
        $categoryEntity->setVisible(false);
        $categoryEntity->setPath('|navigationCategoryId|');

        $blog = $this->getBlogEntity([], [$categoryId]);
        $blog->setCategoryTree([$categoryId]);

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntity], []),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        static::assertSame($categoryEntity, $categoryBreadcrumbBuilder->getBlogCategoryByReferrer($categoryId, $blog, $this->channelContext));
    }

    public function testConvertCategoriesToBreadcrumbUrlsWithSeoUrls(): void
    {
        $categoryEntityOne = $this->createNewCategoryEntity(
            '019192b9cd82711482744d7b456b6c01',
            'Home 2',
            [
                'name' => 'Home sweet home 2',
                'breadcrumb' => [
                    '019192b79049727d9d867a3b9a3271b9' => 'Home',
                    '019192b9b58e7184910e7b9eca0eaf93' => 'Industrial',
                    '019192b9b58f70b99d1bc1b77b6aaea7' => 'Tools, Movies & Garden',
                ],
            ]
        );

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntityOne], [$categoryEntityOne]),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $category = $categoryBreadcrumbBuilder->loadCategory('019192b9cd82711482744d7b456b6c01', $this->channelContext->getContext());
        static::assertNotNull($category);
        $result = $categoryBreadcrumbBuilder->getCategoryBreadcrumbUrls($category, $this->channelContext->getContext(), $this->channelContext->getChannel())->getElements();
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home 2', $firstBreadcrumb->name);
        static::assertSame('seoPathInfo/1', $firstBreadcrumb->path);
        static::assertCount(1, $firstBreadcrumb->seoUrls);
    }

    public function testConvertCategoriesToBreadcrumbUrlsWithSeoUrlsOnlyPathInfo(): void
    {
        $categoryEntityOne = $this->createNewCategoryEntity(
            '019192b9cd82711482744d7b456b6c02',
            'Home',
            [
                'name' => 'Home sweet home',
                'breadcrumb' => [
                    '019192b79049727d9d867a3b9a3271b9' => 'Home',
                    '019192b9b58e7184910e7b9eca0eaf93' => 'Industrial',
                    '019192b9b58f70b99d1bc1b77b6aaea7' => 'Tools, Movies & Garden',
                ],
            ]
        );

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntityOne], [$categoryEntityOne]),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $category = $categoryBreadcrumbBuilder->loadCategory('019192b9cd82711482744d7b456b6c02', $this->channelContext->getContext());
        static::assertNotNull($category);
        $result = $categoryBreadcrumbBuilder->getCategoryBreadcrumbUrls($category, $this->channelContext->getContext(), $this->channelContext->getChannel())->getElements();
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home', $firstBreadcrumb->name);
        static::assertSame('pathInfo/1', $firstBreadcrumb->path);
        static::assertCount(1, $firstBreadcrumb->seoUrls);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function breadcrumbWithoutSeoUrlDataProvider(): iterable
    {
        yield 'page category has a navigation fallback path' => [
            CategoryDefinition::TYPE_PAGE,
            'navigation/019192b9cd82711482744d7b456b6c03',
        ];

        yield 'folder category has no navigable path' => [
            CategoryDefinition::TYPE_FOLDER,
            '',
        ];
    }

    #[DataProvider('breadcrumbWithoutSeoUrlDataProvider')]
    public function testConvertCategoriesToBreadcrumbUrlsWithNoSeoUrls(string $categoryType, string $expectedPath): void
    {
        $categoryEntityOne = $this->createNewCategoryEntity(
            '019192b9cd82711482744d7b456b6c03',
            'Home',
            [
                'name' => 'Home sweet home',
                'breadcrumb' => [
                    '019192b79049727d9d867a3b9a3271b9' => 'Home',
                    '019192b9b58e7184910e7b9eca0eaf93' => 'Industrial',
                    '019192b9b58f70b99d1bc1b77b6aaea7' => 'Tools, Movies & Garden',
                ],
            ]
        );
        $categoryEntityOne->setType($categoryType);

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntityOne], [$categoryEntityOne]),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock([]),
            $this->entityRouteResolver,
        );

        $category = $categoryBreadcrumbBuilder->loadCategory('019192b9cd82711482744d7b456b6c03', $this->channelContext->getContext());
        static::assertNotNull($category);
        $result = $categoryBreadcrumbBuilder->getCategoryBreadcrumbUrls($category, $this->channelContext->getContext(), $this->channelContext->getChannel())->getElements();
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home', $firstBreadcrumb->name);
        static::assertSame($expectedPath, $firstBreadcrumb->path);
        static::assertSame([], $firstBreadcrumb->seoUrls);
    }

    // write a test to cover getBlogBreadcrumbUrls method
    public function testGetBlogBreadcrumbUrls(): void
    {
        $categoryEntityOne = $this->createNewCategoryEntity(
            '019192b9cd82711482744d7b456b6c03',
            'Home',
            [
                'name' => 'Home sweet home',
                'breadcrumb' => [
                    '019192b79049727d9d867a3b9a3271b9' => 'Home',
                    '019192b9b58e7184910e7b9eca0eaf93' => 'Industrial',
                    '019192b9b58f70b99d1bc1b77b6aaea7' => 'Tools, Movies & Garden',
                ],
            ]
        );

        $blog = $this->getBlogEntity([], ['019192b9cd82711482744d7b456b6c03']);
        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([$categoryEntityOne], [$categoryEntityOne]),
            $this->getBlogRepositoryMock([$blog], [$blog]),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );

        $result = $categoryBreadcrumbBuilder->getBlogBreadcrumbUrls($blog->getId(), '', $this->channelContext)->getElements();
        $firstBreadcrumb = $result[0];

        static::assertArrayHasKey('0', $result);
        static::assertArrayHasKey('name', (array) $result[0]);
        static::assertArrayHasKey('path', (array) $result[0]);
        static::assertSame('Home sweet home', $firstBreadcrumb->name);
        static::assertSame('navigation/1', $firstBreadcrumb->path);
    }

    public function testGetBlogSeoCategoryWithNoMainCategoryAndNoCategoryIds(): void
    {
        $blogEntity = new BlogEntity();
        $blogEntity->setId(Uuid::randomHex());
        $blogEntity->setMainCategories(new MainCategoryCollection([]));
        $blogEntity->setCategoryIds([]);
        $blogEntity->setStreamIds([]);

        $categoryBreadcrumbBuilder = new CategoryBreadcrumbBuilder(
            $this->getCategoryRepositoryMock([], []),
            $this->getBlogRepositoryMock([], []),
            $this->getConnectionMock(),
            $this->entityRouteResolver,
        );
        $result = $categoryBreadcrumbBuilder->getBlogSeoCategory($blogEntity, $this->channelContext);

        static::assertNull($result);
    }

    /**
     * @param array<int, array{categoryId: string, pathInfo: string, seoPathInfo: string}> $seoUrls
     */
    private function getConnectionMock(array $seoUrls = [
        [
            'categoryId' => '019192b9cd82711482744d7b456b6c01',
            'pathInfo' => 'pathInfo/1',
            'seoPathInfo' => 'seoPathInfo/1',
        ],
        [
            'categoryId' => '019192b9cd82711482744d7b456b6c02',
            'pathInfo' => 'pathInfo/1',
            'seoPathInfo' => '',
        ],
        [
            'categoryId' => '019192b9cd82711482744d7b456b6c03',
            'pathInfo' => 'navigation/1',
            'seoPathInfo' => '',
        ],
    ]): Connection
    {
        $connection = static::createStub(Connection::class);
        $queryBuilder = static::createStub(QueryBuilder::class);
        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn($seoUrls);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return $connection;
    }

    /**
     * @param array<string, mixed> $translated
     */
    private function createNewCategoryEntity(
        string $id,
        string $name,
        array $translated,
    ): CategoryEntity {
        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId($id);
        $categoryEntity->setName($name);
        $categoryEntity->setTranslated($translated);
        $categoryEntity->setType('page');

        return $categoryEntity;
    }

    /**
     * @param array<CategoryEntity> $categoryEntityCollection1
     * @param array<CategoryEntity> $categoryEntityCollection2
     *
     * @return EntityRepository<CategoryCollection>
     */
    private function getCategoryRepositoryMock(array $categoryEntityCollection1, array $categoryEntityCollection2): EntityRepository
    {
        $categoryRepositoryMock = static::createStub(EntityRepository::class);
        $categoryRepositoryMock->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('category', 1, new CategoryCollection($categoryEntityCollection1), null, new Criteria(), $this->channelContext->getContext()),
            new EntitySearchResult('category', 1, new CategoryCollection($categoryEntityCollection2), null, new Criteria(), $this->channelContext->getContext()),
        );

        return $categoryRepositoryMock;
    }

    /**
     * @param array<BlogEntity> $blogEntityCollection1
     * @param array<BlogEntity> $blogEntityCollection2
     *
     * @return ChannelRepository<ChannelBlogCollection>
     */
    private function getBlogRepositoryMock(array $blogEntityCollection1, array $blogEntityCollection2): ChannelRepository
    {
        $blogRepositoryMock = static::createStub(ChannelRepository::class);
        $blogRepositoryMock->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('blog', 1, new BlogCollection($blogEntityCollection1), null, new Criteria(), $this->channelContext->getContext()),
            new EntitySearchResult('blog', 1, new BlogCollection($blogEntityCollection2), null, new Criteria(), $this->channelContext->getContext()),
        );

        return $blogRepositoryMock;
    }

    /**
     * @param array<string> $streamIds
     * @param array<string>|null $categoryIds
     */
    private function getBlogEntity(?array $streamIds, ?array $categoryIds): ChannelBlogEntity
    {
        $blog = new ChannelBlogEntity();

        $blog->setId(Uuid::randomHex());
        $blog->setStreamIds($streamIds);
        $blog->setCategoryIds($categoryIds);
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        return $blog;
    }

    private function getChannelContext(): ChannelContext
    {
        $channelEntity = new ChannelEntity();
        $channelEntity->setId(Uuid::randomHex());
        $channelEntity->setTypeId(Defaults::CHANNEL_TYPE_FRONTEND);
        $channelEntity->setNavigationCategoryId('navigationCategoryId');
        $channelEntity->setServiceCategoryId('serviceCategoryId');
        $channelEntity->setFooterCategoryId('footerCategoryId');

        return Generator::generateChannelContext(channel: $channelEntity);
    }
}
