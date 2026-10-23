<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Category\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class CategoryBreadcrumbBuilderTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    private ChannelContext $channelContext;

    private IdsCollection $ids;

    private string $deLanguageId;

    private CategoryBreadcrumbBuilder $breadcrumbBuilder;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<TaxCollection>
     */
    private EntityRepository $taxRepository;

    private KernelBrowser $browser;

    private AbstractChannelContextFactory $contextFactory;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->deLanguageId = $this->getDeDeLanguageId();
        $this->breadcrumbBuilder = static::getContainer()->get(CategoryBreadcrumbBuilder::class);

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->createTestData('navigation'),
            'serviceCategoryId' => $this->createTestData('service'),
            'footerCategoryId' => $this->createTestData('footer'),
        ]);

        $this->assignChannelContext($this->browser);

        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->taxRepository = static::getContainer()->get('tax.repository');

        $this->taxRepository->create(
            [
                [
                    'id' => $this->ids->create('tax'),
                    'taxRate' => 19,
                    'name' => 'tax',
                ],
            ],
            Context::createDefaultContext()
        );

        $this->contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->channelContext = $this->contextFactory->create('', $this->ids->get('sales-channel'));
    }

    /**
     * @return iterable<array<string|bool>>
     */
    public static function breadcrumbDataProvider(): iterable
    {
        yield ['navigation', true, false];
        yield ['navigation', true, true];
        yield ['navigation', false, true];

        yield ['service', true, false];
        yield ['service', true, true];
        yield ['service', false, true];

        yield ['footer', true, false];
        yield ['footer', true, true];
        yield ['footer', false, true];
    }

    /**
     * @return iterable<array<bool>>
     */
    public static function seoCategoryProvider(): iterable
    {
        yield [false, false, false];
        yield [false, false, false];
        yield [false, false, true];
        yield [false, true, false];
        yield [false, true, true];
        yield [true, false, false];
        yield [true, false, true];
        yield [true, true, false];
        yield [true, true, true];
    }

    #[DataProvider('breadcrumbDataProvider')]
    public function testIsWithoutEntrypoint(string $key, bool $withChannel, bool $withCategoryId = false): void
    {
        $categoryId = $withCategoryId ? $this->ids->get($key) : null;
        $channel = $withChannel ? $this->channelContext->getChannel() : null;

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search(new Criteria($this->ids->prefixed($key)), new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        ))->getEntities();

        $category1 = $categories->get($this->ids->get($key));
        $category2 = $categories->get($this->ids->get($key . '-a-1'));
        $category3 = $categories->get($this->ids->get($key . '-a-2'));
        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(CategoryEntity::class, $category3);

        $result1 = $this->breadcrumbBuilder->build($category1, $channel, $categoryId);
        $result2 = $this->breadcrumbBuilder->build($category2, $channel, $categoryId);
        $result3 = $this->breadcrumbBuilder->build($category3, $channel, $categoryId);

        static::assertIsArray($result1);
        static::assertIsArray($result2);
        static::assertIsArray($result3);
        static::assertCount(0, $result1);
        static::assertSame(['EN-A'], array_values($result2));
        static::assertSame(['EN-A', 'EN-AA'], array_values($result3));

        /** @var CategoryCollection $categories */
        $categories = $this->categoryRepository->search(new Criteria($this->ids->prefixed($key)), new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->deLanguageId]
        ))->getEntities();

        $category1 = $categories->get($this->ids->get($key));
        $category2 = $categories->get($this->ids->get($key . '-a-1'));
        $category3 = $categories->get($this->ids->get($key . '-a-2'));
        static::assertInstanceOf(CategoryEntity::class, $category1);
        static::assertInstanceOf(CategoryEntity::class, $category2);
        static::assertInstanceOf(CategoryEntity::class, $category3);

        $result1 = $this->breadcrumbBuilder->build($category1, $channel, $categoryId);
        $result2 = $this->breadcrumbBuilder->build($category2, $channel, $categoryId);
        $result3 = $this->breadcrumbBuilder->build($category3, $channel, $categoryId);

        static::assertIsArray($result1);
        static::assertIsArray($result2);
        static::assertIsArray($result3);
        static::assertCount(0, $result1);
        static::assertSame(['DE-A'], array_values($result2));
        static::assertSame(['DE-A', 'DE-AA'], array_values($result3));
    }

    #[DataProvider('seoCategoryProvider')]
    public function testItHasSeoCategory(bool $hasCategories, bool $hasMainCategory, bool $hasMainCategory2ndChannel): void
    {
        $this->createTestData('navigation-sc2');
        $this->createChannel([
            'id' => $this->ids->create('sales-channel-2'),
            'navigationCategoryId' => $this->ids->get('navigation-sc2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://test.to',
                ],
            ],
        ]);

        $blogData = [
            [
                'id' => $this->ids->get('seo-blog'),
                'active' => true,
                'weight' => 999,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                    ['channelId' => $this->ids->get('sales-channel-2'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];

        if ($hasCategories) {
            $blogData[0]['categories'] = [
                ['id' => $this->ids->get('navigation-a-1')],
                ['id' => $this->ids->get('navigation-a-2')],
                ['id' => $this->ids->get('navigation-sc2-a-1')],
                ['id' => $this->ids->get('navigation-sc2-a-2')],
            ];
        }

        if ($hasMainCategory) {
            $blogData[0]['mainCategories'][] = [
                'categoryId' => $this->ids->get('navigation-a-1'),
                'id' => Uuid::randomHex(),
                'channelId' => $this->channelContext->getChannelId(),
            ];
        }

        if ($hasMainCategory2ndChannel) {
            $blogData[0]['mainCategories'][] = [
                'categoryId' => $this->ids->get('navigation-sc2-a-1'),
                'id' => Uuid::randomHex(),
                'channelId' => $this->ids->get('sales-channel-2'),
            ];
        }

        $this->createTestBlog($blogData);

        $criteria = new Criteria([$this->ids->get('seo-blog')]);
        $criteria->addAssociation('categories');

        /** @var BlogEntity $blog */
        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        $this->createBlogStreams();
        $this->createCategoryStreams();
        $blog->setStreamIds([$this->ids->create('stream_id_1')]);

        $category = $this->breadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext);
        $category2 = $this->breadcrumbBuilder->getBlogSeoCategory($blog, $this->contextFactory->create('', $this->ids->get('sales-channel-2')));

        static::assertInstanceOf(CategoryCollection::class, $blog->getCategories());

        if ($hasCategories) {
            static::assertInstanceOf(CategoryEntity::class, $category);
            static::assertInstanceOf(CategoryEntity::class, $category2);
            static::assertNotCount(0, $blog->getCategories());

            if ($hasMainCategory) {
                static::assertSame($this->ids->get('navigation-a-1'), $category->getId());
            } else {
                static::assertTrue(\in_array($category->getId(), $this->ids->prefixed('navigation-a'), true));
            }

            if ($hasMainCategory2ndChannel) {
                static::assertSame($this->ids->get('navigation-sc2-a-1'), $category2->getId());
            } else {
                static::assertTrue(\in_array($category2->getId(), $this->ids->prefixed('navigation-sc2-a'), true));
            }
        } else {
            static::assertCount(0, $blog->getCategories());
            static::assertNull($category);
            static::assertNull($category2);
        }
    }

    public function testApiResponseHasSeoCategory(): void
    {
        $this->createTestData('navigation-test');
        $this->createBlogStreams();
        $this->createCategoryStreams();

        $blogId = $this->ids->get('pid');
        $this->createTestBlog([
            [
                'id' => $blogId,
            ],
        ]);

        $this->getBrowser()->jsonRequest('PATCH', '/api/blog/' . $blogId, [
            'categories' => [
                ['id' => $this->ids->get('navigation-a-2')],
                ['id' => $this->ids->get('navigation-test-a-2')],
            ],
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(204, $response->getStatusCode(), $response->getContent());

        $this->updateBlogStream($blogId, $this->ids->create('stream_id_1'));

        $this->browser->jsonRequest('POST', '/store-api/blog/' . $blogId);
        $response = $this->browser->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($json);
        static::assertArrayHasKey('blog', $json);
        static::assertArrayHasKey('seoCategory', $json['blog']);
        static::assertNotCount(0, $json['blog']['seoCategory']);
        static::assertSame($this->ids->get('navigation-a-2'), $json['blog']['seoCategory']['id']);
    }

    public function testSeoCategoryInheritance(): void
    {
        $optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'blue' => Uuid::randomHex(),
            'yellow' => Uuid::randomHex(),
        ];
        $groupId = Uuid::randomHex();

        $this->createTestBlog([
            [
                'id' => $this->ids->get('variant-blog'),
                'categories' => [
                    ['id' => $this->ids->get('navigation-a-2')],
                    ['id' => $this->ids->get('navigation-b-1')],
                    ['id' => $this->ids->get('navigation-b-2')],
                ],
                'mainCategories' => [
                    [
                        'categoryId' => $this->ids->get('navigation-a-2'),
                        'id' => Uuid::randomHex(),
                        'channelId' => $this->ids->get('sales-channel'),
                    ],
                ],
                'configuratorGroupConfig' => [
                    [
                        'id' => $groupId,
                        'expressionForListings' => true,
                        'representation' => 'box',
                    ],
                ],
                'configuratorSettings' => [
                    [
                        'option' => [
                            'id' => $optionIds['red'],
                            'name' => 'Red',
                            'group' => [
                                'id' => $groupId,
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $optionIds['green'],
                            'name' => 'Green',
                            'group' => [
                                'id' => $groupId,
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $optionIds['blue'],
                            'name' => 'Blue',
                            'group' => [
                                'id' => $groupId,
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $optionIds['yellow'],
                            'name' => 'Yellow',
                            'group' => [
                                'id' => $groupId,
                                'name' => 'Color',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => $this->ids->get('variant-blog-1'),
                'mainCategories' => [
                    [
                        'categoryId' => $this->ids->get('navigation-b-2'),
                        'id' => Uuid::randomHex(),
                        'channelId' => $this->ids->get('sales-channel'),
                    ],
                ],
                'blogNumber' => 'v-1',
                'stock' => 1,
                'active' => true,
                'parentId' => $this->ids->get('variant-blog'),
                'options' => [
                    ['id' => $optionIds['red']],
                ],
            ],
            [
                'id' => $this->ids->get('variant-blog-2'),
                'mainCategories' => [
                    [
                        'categoryId' => $this->ids->get('navigation-b-1'),
                        'id' => Uuid::randomHex(),
                        'channelId' => $this->ids->get('sales-channel'),
                    ],
                ],
                'blogNumber' => 'v-2',
                'stock' => 1,
                'active' => true,
                'parentId' => $this->ids->get('variant-blog'),
                'options' => [
                    ['id' => $optionIds['green']],
                ],
            ],
            [
                'id' => $this->ids->get('variant-blog-3'),
                'categories' => [
                    ['id' => $this->ids->get('navigation-a-1')],
                ],
                'blogNumber' => 'v-3',
                'stock' => 1,
                'active' => true,
                'parentId' => $this->ids->get('variant-blog'),
                'options' => [
                    ['id' => $optionIds['blue']],
                ],
            ],
            [
                'id' => $this->ids->get('variant-blog-4'),
                'categories' => [
                    ['id' => $this->ids->get('navigation-a-2')],
                    ['id' => $this->ids->get('navigation-b-1')],
                ],
                'blogNumber' => 'v-4',
                'stock' => 1,
                'active' => true,
                'parentId' => $this->ids->get('variant-blog'),
                'options' => [
                    ['id' => $optionIds['yellow']],
                ],
            ],
        ], false);

        $this->updateBlogStream($this->ids->get('variant-blog-3'), $this->ids->get('stream_id_1'));

        /** @var BlogEntity $mainBlog */
        $mainBlog = $this->blogRepository->search($this->createSeoCriteria([$this->ids->get('variant-blog')]), Context::createDefaultContext())->getEntities()->first();
        $categoryMain = $this->breadcrumbBuilder->getBlogSeoCategory($mainBlog, $this->channelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryMain);
        static::assertSame($this->ids->get('navigation-a-2'), $categoryMain->getId());
        static::assertSame('EN-AA', $categoryMain->getName());

        /** @var BlogEntity $variant1 */
        $variant1 = $this->blogRepository->search($this->createSeoCriteria([$this->ids->get('variant-blog-1')]), Context::createDefaultContext())->getEntities()->first();
        $categoryVariant1 = $this->breadcrumbBuilder->getBlogSeoCategory($variant1, $this->channelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryVariant1);
        static::assertSame($this->ids->get('navigation-b-2'), $categoryVariant1->getId());
        static::assertSame('EN-BA', $categoryVariant1->getName());

        /** @var BlogEntity $variant2 */
        $variant2 = $this->blogRepository->search($this->createSeoCriteria([$this->ids->get('variant-blog-2')]), Context::createDefaultContext())->getEntities()->first();
        $categoryVariant2 = $this->breadcrumbBuilder->getBlogSeoCategory($variant2, $this->channelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryVariant2);
        static::assertSame($this->ids->get('navigation-b-1'), $categoryVariant2->getId());
        static::assertSame('EN-B', $categoryVariant2->getName());

        /** @var BlogEntity $variant3 */
        $variant3 = $this->blogRepository->search($this->createSeoCriteria([$this->ids->get('variant-blog-3')]), Context::createDefaultContext())->getEntities()->first();
        $categoryVariant3 = $this->breadcrumbBuilder->getBlogSeoCategory($variant3, $this->channelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryVariant3);
        static::assertSame($this->ids->get('navigation-a-1'), $categoryVariant3->getId());
        static::assertSame('EN-A', $categoryVariant3->getName());

        /** @var BlogEntity $variant4 */
        $variant4 = $this->blogRepository->search($this->createSeoCriteria([$this->ids->get('variant-blog-4')]), Context::createDefaultContext())->getEntities()->first();
        $categoryVariant4 = $this->breadcrumbBuilder->getBlogSeoCategory($variant4, $this->channelContext);

        static::assertInstanceOf(CategoryEntity::class, $categoryVariant4);
        static::assertSame($this->ids->get('navigation-a-2'), $categoryVariant4->getId());
        static::assertSame('EN-AA', $categoryVariant4->getName());
    }

    public function testGetBlogSeoCategoryWithInactiveCategory(): void
    {
        // create and retrieve blog and categories
        $blogData = [
            [
                'id' => $this->ids->get('seo-blog'),
                'categories' => [
                    ['id' => $this->ids->get('navigation-a-1')],
                    ['id' => $this->ids->get('navigation-a-2')],
                ],
            ],
        ];
        $this->createTestBlog($blogData);

        $this->updateBlogStream($this->ids->get('seo-blog'), $this->ids->get('stream_id_1'));

        $criteria = new Criteria([$this->ids->get('seo-blog')]);
        $criteria->addAssociation('categories');
        /** @var BlogEntity $blog */
        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        // test if you get at least one category if both are active
        $seoCategory = $this->breadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext);
        static::assertInstanceOf(CategoryEntity::class, $seoCategory);
        static::assertTrue(\in_array($seoCategory->getId(), $this->ids->prefixed('navigation-a'), true));

        // test if you only get the active category
        $this->categoryRepository->update(
            [[
                'id' => $this->ids->get('navigation-a-2'),
                'active' => false,
            ]],
            Context::createDefaultContext()
        );
        $seoCategory = $this->breadcrumbBuilder->getBlogSeoCategory($blog, $this->channelContext);
        static::assertInstanceOf(CategoryEntity::class, $seoCategory);
        static::assertSame($this->ids->get('navigation-a-1'), $seoCategory->getId());
    }

    /**
     * @param array<string> $ids
     */
    private function createSeoCriteria(array $ids): Criteria
    {
        $criteria = new Criteria($ids);
        $criteria->addAssociation('mainCategories.category');

        return $criteria;
    }

    private function createTestData(string $key): string
    {
        $data = [
            [
                'id' => $this->ids->create($key),
                'translations' => [
                    ['name' => 'EN-Entry', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'DE-Entry', 'languageId' => $this->deLanguageId],
                ],
                'children' => [
                    [
                        'id' => $this->ids->create($key . '-a-1'),
                        'translations' => [
                            ['name' => 'EN-A', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                            ['name' => 'DE-A', 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $this->ids->create($key . '-a-2'),
                                'translations' => [
                                    ['name' => 'EN-AA', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                    ['name' => 'DE-AA', 'languageId' => $this->deLanguageId],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $this->ids->create($key . '-b-1'),
                        'translations' => [
                            ['name' => 'EN-B', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                            ['name' => 'DE-B', 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $this->ids->create($key . '-b-2'),
                                'translations' => [
                                    ['name' => 'EN-BA', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                    ['name' => 'DE-BA', 'languageId' => $this->deLanguageId],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('category.repository')->create($data, Context::createDefaultContext());

        return $this->ids->get($key);
    }

    /**
     * @param array<mixed> $blogs
     */
    private function createTestBlog(array $blogs = [], bool $fillAll = true): void
    {
        $basicPayload = [
            'id' => Uuid::randomHex(),
            'name' => 'foo bar',
            'manufacturer' => [
                'id' => Uuid::randomHex(),
                'name' => 'amazing brand',
            ],
            'blogNumber' => 'P1234',
            'taxId' => $this->ids->get('tax'),
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 10,
                    'net' => 12,
                    'linked' => false,
                ],
            ],
            'stock' => 0,
            'weight' => 998,
            'active' => true,
            'type' => BlogDefinition::TYPE_PHYSICAL,
            'visibilities' => [
                ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        if ($fillAll) {
            foreach ($blogs as $k => $v) {
                $blogs[$k] = array_merge($basicPayload, $v);
            }
        } else {
            $blogs[0] = array_merge($basicPayload, $blogs[0]);
        }

        $this->blogRepository->create($blogs, $this->channelContext->getContext());
    }

    private function createBlogStreams(): void
    {
        $stream = [
            'id' => $this->ids->create('stream_id_1'),
            'name' => 'test',
            'filters' => [
                [
                    'type' => 'equals',
                    'field' => 'weight',
                    'value' => '999',
                    'parameters' => [
                        'operator' => 'eq',
                    ],
                ],
            ],
        ];

        $blogStreamsRepository = static::getContainer()->get('blog_stream.repository');
        $blogStreamsRepository->create([$stream], $this->channelContext->getContext());
    }

    private function createCategoryStreams(): string
    {
        $data = [
            'id' => $this->ids->create('category_stream_id_1'),
            'blogStreamId' => $this->ids->get('stream_id_1'),
            'blogAssignmentType' => CategoryDefinition::BLOG_ASSIGNMENT_TYPE_BLOG_STREAM,
            'active' => true,
            'name' => 'Home',
        ];

        static::getContainer()->get('category.repository')->create([$data], Context::createDefaultContext());

        return $this->ids->get('category_stream_id_1');
    }

    private function updateBlogStream(string $blogId, string $streamId): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement(
            'UPDATE `blog` SET `stream_ids` = :streamIds WHERE `id` = :id',
            [
                'streamIds' => json_encode([$streamId], \JSON_THROW_ON_ERROR),
                'id' => Uuid::fromHexToBytes($blogId),
            ]
        );
    }
}
