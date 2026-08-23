<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\SeoUrl;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class SeoUrlTest extends TestCase
{
    use FrontendChannelTestHelper;
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<LandingPageCollection>
     */
    private EntityRepository $landingPageRepository;

    protected function setUp(): void
    {
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
