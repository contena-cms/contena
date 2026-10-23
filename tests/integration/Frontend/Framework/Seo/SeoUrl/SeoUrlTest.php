<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\SeoUrl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
class SeoUrlTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;
    use ChannelApiTestBehaviour;
    use FrontendChannelTestHelper;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<LandingPageCollection>
     */
    private EntityRepository $landingPageRepository;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->landingPageRepository = static::getContainer()->get('landing_page.repository');
    }

    public function testSearchLandingPage(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = $this->createTestLandingPage(['channels' => [
            [
                'id' => $channelContext->getChannelId(),
            ],
        ]]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        $landingPage = $this->landingPageRepository->search($criteria, $channelContext->getContext())->getEntities()->first();
        static::assertNotNull($landingPage);

        $seoUrls = $landingPage->getSeoUrls();
        static::assertNotNull($seoUrls);

        $seoUrl = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $seoUrl);
        static::assertSame('coolUrl', $seoUrl->getSeoPathInfo());
    }

    public function testLandingPageUpdate(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = $this->createTestLandingPage(['channels' => [
            [
                'id' => $channelContext->getChannelId(),
            ],
        ]]);

        $this->landingPageRepository->update(
            [
                [
                    'id' => $id,
                    'url' => 'newUrl',
                ],
            ],
            $channelContext->getContext()
        );

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        $first = $this->landingPageRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($first);

        $urls = $first->getSeoUrls();
        static::assertNotNull($urls);

        // Old seo url
        $seoUrl = $urls->filterByProperty('seoPathInfo', 'coolUrl')->first();
        static::assertNotNull($seoUrl);

        static::assertNull($seoUrl->getIsCanonical());
        static::assertFalse($seoUrl->getIsDeleted());

        static::assertSame('/landingPage/' . $id, $seoUrl->getPathInfo());
        static::assertSame($id, $seoUrl->getForeignKey());

        /** @var SeoUrlCollection $urls */
        $urls = $first->getSeoUrls();

        // New seo url
        $seoUrl = $urls->filterByProperty('seoPathInfo', 'newUrl')->first();
        static::assertNotNull($seoUrl);

        static::assertTrue($seoUrl->getIsCanonical());
        static::assertFalse($seoUrl->getIsDeleted());

        static::assertSame('/landingPage/' . $id, $seoUrl->getPathInfo());
        static::assertSame($id, $seoUrl->getForeignKey());
    }

    public function testSearchBlog(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $id = $this->createTestBlog(channelId: $channelId);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var BlogEntity $blog */
        $blog = $this->blogRepository->search($criteria, $channelContext->getContext())->getEntities()->first();

        static::assertInstanceOf(SeoUrlCollection::class, $blog->getSeoUrls());

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $blog->getSeoUrls();
        $seoUrl = $seoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $seoUrl);
        static::assertSame('foo-bar/P1234', $seoUrl->getSeoPathInfo());
    }

    public function testSearchBlogForHeadlessChannelHasCorrectUrl(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createChannelContext(
            [
                'id' => $channelId,
                'name' => 'test',
                'typeId' => Defaults::CHANNEL_TYPE_API,
            ]
        );

        $id = $this->createTestBlog(channelId: $channelId);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        /** @var BlogEntity $blog */
        $blog = $this->blogRepository->search($criteria, $channelContext->getContext())->getEntities()->first();

        static::assertInstanceOf(SeoUrlCollection::class, $blog->getSeoUrls());

        /** @var SeoUrlCollection $seoUrls */
        $seoUrls = $blog->getSeoUrls();
        static::assertCount(0, $seoUrls);
    }

    public function testSearchCategory(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $categoryRepository = static::getContainer()->get('category.repository');

        $rootId = Uuid::randomHex();
        $childAId = Uuid::randomHex();
        $childA1Id = Uuid::randomHex();

        $categoryRepository->create([[
            'id' => $rootId,
            'name' => 'root',
            'children' => [
                [
                    'id' => $childAId,
                    'name' => 'a',
                    'children' => [
                        [
                            'id' => $childA1Id,
                            'name' => '1',
                        ],
                    ],
                ],
            ],
        ]], Context::createDefaultContext());
        $this->runWorker();

        $context = $channelContext->getContext();

        $cases = [
            ['expected' => null, 'categoryId' => $childAId],
            ['expected' => null, 'categoryId' => $childA1Id],
        ];

        $this->runChecks($cases, $categoryRepository, $context, $channelId);
    }

    public function testSearchCategoryWithLink(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext($channelId, 'test');

        $categoryRepository = static::getContainer()->get('category.repository');

        $categoryPageId = Uuid::randomHex();
        $categoryPage = [
            [
                'id' => $categoryPageId,
                'name' => 'page',
                'type' => 'page',
            ],
        ];

        $categoryLinkId = Uuid::randomHex();
        $categoryLink = [
            [
                'id' => $categoryLinkId,
                'name' => 'link',
                'type' => 'link',
            ],
        ];

        $categories = [...$categoryLink, ...$categoryPage];
        $categoryRepository->create($categories, Context::createDefaultContext());
        $this->runWorker();

        $context = $channelContext->getContext();

        $cases = [
            ['expected' => null, 'categoryId' => $categoryPageId],
            ['expected' => null, 'categoryId' => $categoryLinkId],
        ];

        $this->runChecks($cases, $categoryRepository, $context, $channelId);
    }

    public function testSearchCategoryWithChannelEntryPoint(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext(
            $channelId,
            'test'
        );

        $categoryRepository = static::getContainer()->get('category.repository');

        $rootId = Uuid::randomHex();
        $childAId = Uuid::randomHex();
        $childA1Id = Uuid::randomHex();
        $childA1ZId = Uuid::randomHex();

        $categoryRepository->create([[
            'id' => $rootId,
            'name' => 'root',
            'children' => [
                [
                    'id' => $childAId,
                    'name' => 'a',
                    'children' => [
                        [
                            'id' => $childA1Id,
                            'name' => '1',
                            'children' => [
                                [
                                    'id' => $childA1ZId,
                                    'name' => 'z',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]], Context::createDefaultContext());

        $this->updateChannelNavigationEntryPoint($channelId, $childAId);
        $this->runWorker();

        $context = $channelContext->getContext();

        $cases = [
            ['expected' => '1/', 'categoryId' => $childA1Id],
            ['expected' => '1/z/', 'categoryId' => $childA1ZId],
        ];

        $this->runChecks($cases, $categoryRepository, $context, $channelId);
    }

    public function testSearchCategoryWithComplexHierarchy(): void
    {
        $channelId = Uuid::randomHex();
        $channelContext = $this->createFrontendChannelContext(
            $channelId,
            'test'
        );

        $categoryRepository = static::getContainer()->get('category.repository');

        $rootId = Uuid::randomHex();
        $childAId = Uuid::randomHex();
        $childA1Id = Uuid::randomHex();
        $childA1ZId = Uuid::randomHex();
        $childBId = Uuid::randomHex();
        $childB1Id = Uuid::randomHex();
        $childB1ZId = Uuid::randomHex();

        $categoryRepository->create([[
            'id' => $rootId,
            'name' => 'root',
            'children' => [
                [
                    'id' => $childAId,
                    'name' => 'a',
                    'children' => [
                        [
                            'id' => $childA1Id,
                            'name' => '1',
                            'children' => [
                                [
                                    'id' => $childA1ZId,
                                    'name' => 'z',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => $childBId,
                    'name' => 'b',
                    'children' => [
                        [
                            'id' => $childB1Id,
                            'name' => '2',
                            'children' => [
                                [
                                    'id' => $childB1ZId,
                                    'name' => 'y',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]], Context::createDefaultContext());

        $context = $channelContext->getContext();

        // We are updating the sales channel entry point without running a worker task. We expect the root category url
        // to change, while all other urls will be recreated in an asynch worker task.
        $this->updateChannelNavigationEntryPoint($channelId, $rootId);
        $this->runChecks([], $categoryRepository, $context, $channelId);

        $this->runWorker();
        $casesRoot = [
            ['expected' => null, 'categoryId' => $rootId],
            ['expected' => 'b/', 'categoryId' => $childBId],
            ['expected' => 'b/2/y/', 'categoryId' => $childB1ZId],
            ['expected' => 'a/', 'categoryId' => $childAId],
            ['expected' => 'a/1/z/', 'categoryId' => $childA1ZId],
        ];
        $this->runChecks($casesRoot, $categoryRepository, $context, $channelId);

        $this->updateChannelNavigationEntryPoint($channelId, $childAId);
        $this->runWorker();
        $casesA = [
            ['expected' => null, 'categoryId' => $rootId],
            ['expected' => '1/', 'categoryId' => $childA1Id],
            ['expected' => '1/z/', 'categoryId' => $childA1ZId],
        ];
        $this->runChecks($casesA, $categoryRepository, $context, $channelId);

        $this->updateChannelNavigationEntryPoint($channelId, $rootId);
        $this->runWorker();
        $this->runChecks($casesRoot, $categoryRepository, $context, $channelId);
    }

    public function testSearchWithLimit(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepo */
        $blogRepo = static::getContainer()->get('blog.repository');

        $blogRepo->create([[
            'id' => Uuid::randomHex(),
            'name' => 'foo bar',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'blogNumber' => Uuid::randomHex(),
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false],
            ],
            'stock' => 0,
        ]], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->getAssociation('seoUrls')->setLimit(10);

        /** @var BlogEntity $blog */
        $blog = $blogRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(SeoUrlCollection::class, $blog->getSeoUrls());
    }

    public function testSearchWithFilter(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepo */
        $blogRepo = static::getContainer()->get('blog.repository');

        $blogRepo->create([[
            'id' => Uuid::randomHex(),
            'name' => 'foo bar',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'blogNumber' => Uuid::randomHex(),
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false],
            ],
            'stock' => 0,
            'seoUrls' => [
                [
                    'id' => Uuid::randomHex(),
                    'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => 'foo',
                    'seoPathInfo' => 'asdf',
                ],
            ],
        ]], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addFilter(new EqualsFilter('blog.seoUrls.isCanonical', null));

        $criteria->getAssociation('seoUrls')
            ->setLimit(10)
            ->addFilter(new EqualsFilter('isCanonical', null));

        $blogs = $blogRepo->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertNotCount(0, $blogs);

        /** @var BlogEntity $blog */
        $blog = $blogs->first();
        static::assertInstanceOf(SeoUrlCollection::class, $blog->getSeoUrls());
    }

    public function testInsert(): void
    {
        $seoUrlId1 = Uuid::randomHex();
        $seoUrlId2 = Uuid::randomHex();

        // The default test sales channel is a headless channel; mark its domain as an external frontend so
        // SEO URLs are generated for it (otherwise reindexing sweeps the inserted canonical URLs as deleted).
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE `channel_domain` SET `is_external_frontend` = 1 WHERE `channel_id` = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL)]
        );

        $id = Uuid::randomHex();
        $this->upsertBlog([
            'id' => $id,
            'name' => 'awesome blog',
            'seoUrls' => [
                [
                    'id' => $seoUrlId1,
                    'channelId' => TestDefaults::CHANNEL,
                    'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => '/detail/' . $id,
                    'seoPathInfo' => 'awesome v2',
                    'isCanonical' => true,
                    'isModified' => true,
                ],
                [
                    'id' => $seoUrlId2,
                    'channelId' => TestDefaults::CHANNEL,
                    'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => '/detail/' . $id,
                    'seoPathInfo' => 'awesome',
                    'isCanonical' => null,
                    'isModified' => true,
                ],
            ],
            'visibilities' => [
                [
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        $first = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($first);

        $seoUrls = $first->getSeoUrls();
        static::assertNotNull($seoUrls);

        $seoUrl = $seoUrls->filterByProperty('id', $seoUrlId1)->first();
        static::assertNotNull($seoUrl);

        static::assertTrue($seoUrl->getIsCanonical());
        static::assertFalse($seoUrl->getIsDeleted());

        static::assertSame('awesome v2', $seoUrl->getSeoPathInfo());
    }

    public function testUpdate(): void
    {
        $seoUrlId = Uuid::randomHex();
        $id = Uuid::randomHex();
        $this->upsertBlog(['id' => $id, 'name' => 'awesome blog']);

        $router = static::getContainer()->get('router');
        $pathInfo = $router->generate(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $id]);

        $this->upsertBlog([
            'id' => $id,
            'seoUrls' => [
                [
                    'id' => $seoUrlId,
                    'routeName' => BlogPageSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => $pathInfo,
                    'seoPathInfo' => 'awesome',
                    'isCanonical' => true,
                ],
            ],
        ]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('seoUrls');

        $first = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($first);

        $urls = $first->getSeoUrls();
        static::assertNotNull($urls);

        $seoUrl = $urls->filterByProperty('id', $seoUrlId)->first();
        static::assertNotNull($seoUrl);

        static::assertTrue($seoUrl->getIsCanonical());
        static::assertFalse($seoUrl->getIsDeleted());

        static::assertSame('/detail/' . $id, $seoUrl->getPathInfo());
        static::assertSame($id, $seoUrl->getForeignKey());
    }

    /**
     * @param array<array{expected: string|null, categoryId: string}> $cases
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    private function runChecks(array $cases, EntityRepository $categoryRepository, Context $context, string $channelId): void
    {
        foreach ($cases as $case) {
            $criteria = new Criteria([$case['categoryId']]);
            $criteria->addAssociation('seoUrls');

            /** @var CategoryEntity $category */
            $category = $categoryRepository->search($criteria, $context)->getEntities()->first();
            static::assertSame($case['categoryId'], $category->getId());

            /** @var SeoUrlCollection $seoUrls */
            $seoUrls = $category->getSeoUrls();
            static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

            if ($category->getType() === CategoryDefinition::TYPE_LINK) {
                /** @var SeoUrlCollection $urls */
                $urls = $category->getSeoUrls();
                static::assertCount(0, $urls);

                continue;
            }

            $seoUrls = $seoUrls->filterByProperty('channelId', $channelId);
            $expectedCount = $case['expected'] === null ? 0 : 1;
            static::assertCount($expectedCount, $seoUrls->filterByProperty('isCanonical', true));

            if ($expectedCount === 0) {
                continue;
            }

            /** @var SeoUrlEntity $canonicalUrl */
            $canonicalUrl = $seoUrls
                ->filterByProperty('isCanonical', true)
                ->filterByProperty('channelId', $channelId)
                ->first();
            static::assertInstanceOf(SeoUrlEntity::class, $canonicalUrl);
            static::assertSame($case['expected'], $canonicalUrl->getSeoPathInfo());
        }
    }

    /**
     * @param array<string, array<int, array<string, bool|int|string|null>>|string> $data
     */
    private function upsertBlog(array $data): EntityWrittenContainerEvent
    {
        $defaults = [
            'blogNumber' => Uuid::randomHex(),
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
            'stock' => 0,
        ];
        $data = array_merge($defaults, $data);

        return $this->blogRepository->upsert([$data], Context::createDefaultContext());
    }

    /**
     * @param array<string, string|array<string, mixed>> $overrides
     */
    private function createTestBlog(array $overrides = [], string $channelId = TestDefaults::CHANNEL): string
    {
        $id = Uuid::randomHex();
        $insert = [
            'id' => $id,
            'name' => 'foo bar',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'blogNumber' => 'P1234',
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 12,
                    'linked' => false,
                ],
            ],
            'stock' => 0,
            'visibilities' => [
                [
                    'channelId' => $channelId,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $insert = array_merge($insert, $overrides);

        $this->blogRepository->create([$insert], Context::createDefaultContext());

        return $id;
    }

    /**
     * @param array<string, array<int, array<string, string>>> $overrides
     */
    private function createTestLandingPage(array $overrides = []): string
    {
        $id = Uuid::randomHex();
        $insert = [
            'id' => $id,
            'name' => 'foo bar',
            'url' => 'coolUrl',
        ];

        $insert = array_merge($insert, $overrides);

        $this->landingPageRepository->create([$insert], Context::createDefaultContext());

        return $id;
    }
}
