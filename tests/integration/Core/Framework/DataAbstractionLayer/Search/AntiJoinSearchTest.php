<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class AntiJoinSearchTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');
    }

    public function testManyToMany(): void
    {
        $noTagsId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $redGreenId = Uuid::randomHex();
        $blueId = Uuid::randomHex();

        $blogs = [
            $this->getTaggedBlog($noTagsId, 'not-green hasNoTags'),
            $this->getTaggedBlog($redId, 'red', ['red']),
            $this->getTaggedBlog($greenId, 'green', ['green']),
            $this->getTaggedBlog($redGreenId, 'red and green', ['red', 'green']),
            $this->getTaggedBlog($blueId, 'blue', ['blue']),
        ];

        $ids = array_column($blogs, 'id');

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create($blogs, Context::createDefaultContext());

        $notGreenFilter = new NotFilter('AND', [
            new EqualsFilter('blog.tags.name', 'green'),
        ]);
        $criteria = (new Criteria($ids))
            ->addFilter($notGreenFilter);

        $notGreenIds = $blogRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        static::assertContains($noTagsId, $notGreenIds);
        static::assertContains($redId, $notGreenIds);
        static::assertContains($blueId, $notGreenIds);
        static::assertCount(3, $notGreenIds);

        $extendedNotGreenFilter = new MultiFilter('AND', [
            new ContainsFilter('name', 'green'),
            $notGreenFilter,
        ]);

        $criteria = (new Criteria($ids))->addFilter($extendedNotGreenFilter);

        $notGreenExtendedIds = $blogRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($noTagsId, $notGreenExtendedIds);
        static::assertCount(1, $notGreenExtendedIds);

        $notGreenFilter = new NotFilter('OR', [
            new EqualsFilter('blog.tags.name', 'green'),
            new EqualsFilter('blog.tags.name', 'red'),
        ]);
        $criteria = (new Criteria($ids))->addFilter($notGreenFilter);

        $notGreenOrRedIds = $blogRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($noTagsId, $notGreenOrRedIds);
        static::assertContains($blueId, $notGreenOrRedIds);
        static::assertCount(2, $notGreenOrRedIds);
    }

    public function testMultipleManyToMany(): void
    {
        $noTagsId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $redGreenId = Uuid::randomHex();
        $blueId = Uuid::randomHex();

        $blogs = [
            $this->getTaggedBlog($noTagsId, 'not-green hasNoTags'),
            $this->getTaggedBlog($greenId, 'green', ['green']),
            $this->getTaggedBlog($redId, 'red', ['red']),
            $this->getTaggedBlog($redGreenId, 'red and green', ['red', 'green']),
            $this->getTaggedBlog($blueId, 'blue', ['blue']),
        ];

        $ids = array_column($blogs, 'id');

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create($blogs, Context::createDefaultContext());

        $notGreenOrRed = new MultiFilter('AND', [
            new NotFilter('OR', [new EqualsFilter('blog.tags.name', 'green'), new EqualsFilter('blog.tags.name', 'yellow')]),
            new NotFilter('AND', [new EqualsFilter('blog.tags.name', 'red')]),
        ]);
        $criteria = (new Criteria($ids))->addFilter($notGreenOrRed);
        $notGreenOrRedIds = $blogRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($noTagsId, $notGreenOrRedIds);
        static::assertContains($blueId, $notGreenOrRedIds);
        static::assertCount(2, $notGreenOrRedIds);
    }

    public function testManyToManyWithNormalJoin(): void
    {
        $noTagsId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $redGreenId = Uuid::randomHex();
        $greenBlueId = Uuid::randomHex();

        $blogs = [
            $this->getTaggedBlog($noTagsId, 'not-green hasNoTags'),
            $this->getTaggedBlog($greenId, 'green', ['green']),
            $this->getTaggedBlog($redId, 'red', ['red']),
            $this->getTaggedBlog($redGreenId, 'red and green', ['red', 'green']),
            $this->getTaggedBlog($greenBlueId, 'green blue', ['blue', 'green']),
        ];

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create($blogs, Context::createDefaultContext());

        $greenButNotRed = new MultiFilter('AND', [
            new MultiFilter('OR', [
                new EqualsFilter('blog.name', 'green'),
                new EqualsFilter('blog.tags.name', 'green'),
            ]),
            new NotFilter('AND', [new EqualsFilter('blog.tags.name', 'red')]),
        ]);

        $criteria = (new Criteria())->addFilter($greenButNotRed);

        $greenButNotRedIds = $blogRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($greenId, $greenButNotRedIds);
        static::assertContains($greenBlueId, $greenButNotRedIds);
        static::assertCount(2, $greenButNotRedIds);
    }

    public function testManyToManyToMany(): void
    {
        $ids = new IdsCollection();

        $categories = [
            [
                'id' => $ids->get('no-blogs'),
                'name' => 'no blogs',
            ],
            [
                'id' => $ids->get('no-tags'),
                'name' => 'not tags',
                'blogs' => [
                    $this->getTaggedBlog(Uuid::randomHex(), 'no tags'),
                ],
            ],
            [
                'id' => $ids->get('with-green'),
                'name' => 'green',
                'blogs' => [
                    $this->getTaggedBlog(Uuid::randomHex(), 'not green'),
                    $this->getTaggedBlog(Uuid::randomHex(), 'green', ['green']),
                ],
            ],
            [
                'id' => $ids->get('with-red'),
                'name' => 'red',
                'blogs' => [
                    $this->getTaggedBlog(Uuid::randomHex(), 'red', ['red']),
                ],
            ],
        ];

        /** @var EntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryRepository->create($categories, Context::createDefaultContext());

        $nonGreen = new NotFilter('AND', [
            new EqualsFilter('category.blogs.tags.name', 'green'),
        ]);
        $criteria = (new Criteria($ids->all()))->addFilter($nonGreen);

        $matches = $categoryRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($ids->get('no-blogs'), $matches);
        static::assertContains($ids->get('no-tags'), $matches);
        static::assertContains($ids->get('with-red'), $matches);
        static::assertCount(3, $matches);
    }

    public function testOneToMany(): void
    {
        $noBlogsId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $redGreenId = Uuid::randomHex();
        $ids = [$noBlogsId, $redId, $greenId, $redGreenId];

        $manufacturers = [
            [
                'id' => $noBlogsId,
                'name' => 'no blogs',
            ],
            [
                'id' => $redId,
                'name' => 'red',
                'blogs' => [
                    [
                        'id' => Uuid::randomHex(),
                        'blogNumber' => 'red',
                        'name' => 'red',
                        'stock' => 10,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                    ],
                ],
            ],
            [
                'id' => $greenId,
                'name' => 'green',
                'blogs' => [
                    [
                        'id' => Uuid::randomHex(),
                        'blogNumber' => 'green',
                        'name' => 'green',
                        'stock' => 10,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                    ],
                ],
            ],
            [
                'id' => $redGreenId,
                'name' => 'red green',
                'blogs' => [
                    [
                        'id' => Uuid::randomHex(),
                        'blogNumber' => 'red green',
                        'name' => 'red green',
                        'stock' => 10,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                    ],
                ],
            ],
        ];

        /** @var EntityRepository<BlogManufacturerCollection> $manufacturerRepository */
        $manufacturerRepository = static::getContainer()->get('blog_manufacturer.repository');
        $manufacturerRepository->create($manufacturers, Context::createDefaultContext());

        $notGreenFilter = new NotFilter('AND', [
            new ContainsFilter('blog_manufacturer.blogs.blogNumber', 'green'),
        ]);
        $criteria = (new Criteria($ids))->addFilter($notGreenFilter);

        $notGreenIds = $manufacturerRepository->searchIds($criteria, Context::createDefaultContext())->getIds();
        static::assertContains($noBlogsId, $notGreenIds);
        static::assertContains($redId, $notGreenIds);
        static::assertCount(2, $notGreenIds);
    }

    public function testTranslatedField(): void
    {
        $onlyGreenId = Uuid::randomHex();
        $greenGruenId = Uuid::randomHex();
        $ids = [$onlyGreenId, $greenGruenId];

        $blogs = [
            [
                'id' => $onlyGreenId,
                'blogNumber' => $onlyGreenId,
                'translations' => [
                    'en-GB' => [
                        'name' => 'green',
                    ],
                ],
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $greenGruenId,
                'blogNumber' => $greenGruenId,
                'translations' => [
                    'en-GB' => [
                        'name' => 'green',
                    ],
                    'de-DE' => [
                        'name' => 'grün',
                    ],
                ],
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
        ];

        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create($blogs, Context::createDefaultContext());

        $notGreenFilter = new NotFilter('AND', [new EqualsFilter('blog.name', 'green')]);

        $enGbContext = Context::createDefaultContext();
        $criteria = (new Criteria($ids))->addFilter($notGreenFilter);

        $ids = $blogRepository->searchIds($criteria, $enGbContext)->getIds();
        static::assertIsArray($ids);
        static::assertCount(0, $ids);

        $rawDeContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]);
        $criteria = (new Criteria())->addFilter($notGreenFilter);

        $ids = $blogRepository->searchIds($criteria, $rawDeContext)->getIds();
        static::assertIsArray($ids);
        static::assertContains($greenGruenId, $ids);
        static::assertCount(1, $ids);

        $deContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, 1.0, true);
        $criteria = (new Criteria($ids))->addFilter($notGreenFilter);

        $ids = $blogRepository->searchIds($criteria, $deContext)->getIds();
        static::assertIsArray($ids);
        static::assertContains($greenGruenId, $ids);
        static::assertCount(1, $ids);

        $notGruenFilter = new NotFilter('AND', [new EqualsFilter('blog.name', 'grün')]);

        $enGbContext = Context::createDefaultContext();
        $criteria = (new Criteria($ids))->addFilter($notGruenFilter);

        $ids = $blogRepository->searchIds($criteria, $enGbContext)->getIds();
        static::assertIsArray($ids);
        static::assertContains($greenGruenId, $ids);
        static::assertCount(1, $ids);

        $rawDeContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]);
        $criteria = (new Criteria($ids))->addFilter($notGruenFilter);

        $ids = $blogRepository->searchIds($criteria, $rawDeContext)->getIds();
        static::assertIsArray($ids);
        static::assertCount(0, $ids);

        $deContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, 1.0, true);
        $criteria = (new Criteria())->addFilter($notGruenFilter);

        $ids = $blogRepository->searchIds($criteria, $deContext)->getIds();
        static::assertIsArray($ids);
        static::assertContains($onlyGreenId, $ids);
        static::assertCount(1, $ids);
    }

    public function testManyToManyWithNegatedFilter(): void
    {
        $withoutTagId = Uuid::randomHex();
        $withTagId = Uuid::randomHex();

        $blogs = [
            $this->getTaggedBlog($withoutTagId, 'not-green hasNoTags'),
            $this->getTaggedBlog($withTagId, 'green', ['green']),
        ];

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create($blogs, Context::createDefaultContext());

        $tagsNullFilters = new EqualsFilter('blog.tags.id', null);
        $criteria = (new Criteria())->addFilter($tagsNullFilters);

        $result = $blogRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($withoutTagId, $result);
        static::assertNotContains($withTagId, $result);
        static::assertCount(1, $result);

        $notTagsNullFilters = new NotFilter('AND', [$tagsNullFilters]);
        $criteria = (new Criteria())->addFilter($notTagsNullFilters);

        $result = $blogRepository->searchIds($criteria, Context::createDefaultContext())->getIds();

        static::assertContains($withTagId, $result);
        static::assertNotContains($withoutTagId, $result);
        static::assertCount(1, $result);

        $notNotTagsNullFilters = new NotFilter('AND', [$notTagsNullFilters]);
        $result = $blogRepository->searchIds((new Criteria())->addFilter($notNotTagsNullFilters), Context::createDefaultContext())->getIds();

        static::assertContains($withoutTagId, $result);
        static::assertNotContains($withTagId, $result);
        static::assertCount(1, $result);
    }

    /**
     * @param string[] $tags
     *
     * @return array{
     *     id: string,
     *     blogNumber: string,
     *     name: string,
     *     stock: int,
     *     price: array{currencyId: string, gross: int, net: int, linked: bool}[],
     *     manufacturer: array{name: string},
     *     tax: array{name: string, taxRate: int},
     *     tags: array{name: string}[]
     * }
     */
    private function getTaggedBlog(string $id, string $name, array $tags = []): array
    {
        return [
            'id' => $id,
            'blogNumber' => $id,
            'name' => $name,
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'tags' => array_map(static fn (string $tag) => ['name' => $tag], $tags),
        ];
    }
}
