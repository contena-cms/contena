<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;

/**
 * @internal
 */
class VariantListingIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $repository;

    private string $blogId;

    /**
     * @var array<string, string>
     */
    private array $optionIds = [];

    /**
     * @var array<string, string>
     */
    private array $groupIds = [];

    /**
     * @var array<string, string>
     */
    private array $variantIds = [];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog.repository');
        $this->connection = static::getContainer()->get(Connection::class);

        parent::setUp();
    }

    public function testSingleGroup(): void
    {
        $this->createBlog(['color']);

        $listing = $this->fetchListing();

        static::assertCount(2, $listing->ids);

        static::assertContains($this->optionIds['green'], $listing->optionIds);
        static::assertContains($this->optionIds['red'], $listing->optionIds);
    }

    public function testTwoGroups(): void
    {
        $this->createBlog(['color', 'size']);

        $listing = $this->fetchListing();

        static::assertCount(4, $listing->ids);

        static::assertContains($this->optionIds['green'], $listing->optionIds);
        static::assertContains($this->optionIds['red'], $listing->optionIds);

        static::assertContains($this->optionIds['xl'], $listing->optionIds);
        static::assertContains($this->optionIds['l'], $listing->optionIds);
    }

    public function testAllGroups(): void
    {
        $this->createBlog(['color', 'size', 'material']);

        $listing = $this->fetchListing();
        static::assertCount(8, $listing->ids);
    }

    public function testNoGroup(): void
    {
        $this->createBlog([]);

        $listing = $this->fetchListing();

        static::assertCount(1, $listing->ids);

        $listing = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id FROM blog WHERE display_group IS NOT NULL AND blog.id = :parentId',
            ['parentId' => Uuid::fromHexToBytes($this->blogId)]
        );
        static::assertCount(0, $listing);
    }

    /**
     * @param string[] $listingProperties
     */
    private function createBlog(array $listingProperties): void
    {
        $this->blogId = Uuid::randomHex();

        $this->optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'xl' => Uuid::randomHex(),
            'l' => Uuid::randomHex(),
            'iron' => Uuid::randomHex(),
            'steel' => Uuid::randomHex(),
        ];

        $this->variantIds = [
            'redXlIron' => Uuid::randomHex(),
            'redXlSteel' => Uuid::randomHex(),
            'greenXlIron' => Uuid::randomHex(),
            'greenXlSteel' => Uuid::randomHex(),
            'redLIron' => Uuid::randomHex(),
            'redLSteel' => Uuid::randomHex(),
            'greenLIron' => Uuid::randomHex(),
            'greenLSteel' => Uuid::randomHex(),
        ];

        $this->groupIds = [
            'color' => Uuid::randomHex(),
            'size' => Uuid::randomHex(),
            'material' => Uuid::randomHex(),
        ];

        $config = [];
        foreach ($listingProperties as $groupName) {
            $config[] = [
                'id' => $this->groupIds[$groupName],
                'expressionForListings' => true,
                'representation' => 'box', // box, select, image, color
            ];
        }

        $data = [
            [
                'id' => $this->blogId,
                'variantListingConfig' => [
                    'displayParent' => null,
                    'mainVariantId' => null,
                    'configuratorGroupConfig' => $config,
                ],
                'blogNumber' => 'a.0',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'name' => 'example',
                'active' => true,
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                ],
                'configuratorSettings' => [
                    [
                        'option' => [
                            'id' => $this->optionIds['red'],
                            'name' => 'Red',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['green'],
                            'name' => 'Green',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['xl'],
                            'name' => 'XL',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['l'],
                            'name' => 'L',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['iron'],
                            'name' => 'Iron',
                            'group' => [
                                'id' => $this->groupIds['material'],
                                'name' => 'material',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['steel'],
                            'name' => 'Steel',
                            'group' => [
                                'id' => $this->groupIds['material'],
                                'name' => 'material',
                            ],
                        ],
                    ],
                ],
                'visibilities' => [
                    [
                        'channelId' => TestDefaults::CHANNEL,
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $this->variantIds['redXlIron'],
                'blogNumber' => 'a.1',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['xl']],
                    ['id' => $this->optionIds['iron']],
                ],
            ],
            [
                'id' => $this->variantIds['redXlSteel'],
                'blogNumber' => 'a.2',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['xl']],
                    ['id' => $this->optionIds['steel']],
                ],
            ],
            [
                'id' => $this->variantIds['greenXlIron'],
                'blogNumber' => 'a.3',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['xl']],
                    ['id' => $this->optionIds['iron']],
                ],
            ],
            [
                'id' => $this->variantIds['greenXlSteel'],
                'blogNumber' => 'a.4',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['xl']],
                    ['id' => $this->optionIds['steel']],
                ],
            ],
            [
                'id' => $this->variantIds['redLIron'],
                'blogNumber' => 'a.5',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['l']],
                    ['id' => $this->optionIds['iron']],
                ],
            ],
            [
                'id' => $this->variantIds['redLSteel'],
                'blogNumber' => 'a.6',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['l']],
                    ['id' => $this->optionIds['steel']],
                ],
            ],
            [
                'id' => $this->variantIds['greenLIron'],
                'blogNumber' => 'a.7',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                    ['id' => $this->optionIds['iron']],
                ],
            ],
            [
                'id' => $this->variantIds['greenLSteel'],
                'blogNumber' => 'a.8',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                    ['id' => $this->optionIds['steel']],
                ],
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext());

        $this->runWorker();
    }

}

/**
 * @internal
 */
class Listing
{
    /**
     * @param list<string> $ids
     * @param list<string> $optionIds
     */
    public function __construct(
        public array $ids,
        public array $optionIds
    ) {
    }
}
