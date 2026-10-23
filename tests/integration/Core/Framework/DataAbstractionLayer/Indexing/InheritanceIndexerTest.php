<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class InheritanceIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testManyToOneInheritanceUpdates(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            [
                'id' => $ids->create('parent'),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'name' => 'test',
                'manufacturer' => [
                    'id' => $ids->create('manufacturer'),
                    'name' => 'test',
                ],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false],
                ],
                'stock' => 10,
                'blogNumber' => $ids->get('parent'),
            ],
            [
                'id' => $ids->create('variant-1'),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'blogNumber' => $ids->get('variant-1'),
            ],
            [
                'id' => $ids->create('variant-2'),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'blogNumber' => $ids->get('variant-2'),
            ],
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer'), $ids->get('manufacturer'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), null, $ids->get('manufacturer'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer'));

        // update variant-1 manufacturer, should update only variant-1
        $this->blogRepository->update([
            [
                'id' => $ids->get('variant-1'),
                'manufacturer' => ['id' => $ids->create('manufacturer-2'), 'name' => 'test'],
            ],
        ], Context::createDefaultContext());

        $this->runWorker();

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer'), $ids->get('manufacturer'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), $ids->get('manufacturer-2'), $ids->get('manufacturer-2'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer'));

        // update parent manufacturer - should update parent and variant-2
        $this->blogRepository->update([
            [
                'id' => $ids->get('parent'),
                'manufacturer' => ['id' => $ids->create('manufacturer-3'), 'name' => 'test'],
            ],
        ], Context::createDefaultContext());
        $this->runWorker();

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer-3'), $ids->get('manufacturer-3'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), $ids->get('manufacturer-2'), $ids->get('manufacturer-2'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer-3'));

        // reset variant-1 manufacturer - should update variant-1 with parent data
        $this->blogRepository->update([
            ['id' => $ids->get('variant-1'), 'manufacturerId' => null],
        ], Context::createDefaultContext());
        $this->runWorker();

        $this->assertManufacturerInheritance($ids->get('parent'), $ids->get('manufacturer-3'), $ids->get('manufacturer-3'));
        $this->assertManufacturerInheritance($ids->get('variant-1'), null, $ids->get('manufacturer-3'));
        $this->assertManufacturerInheritance($ids->get('variant-2'), null, $ids->get('manufacturer-3'));
    }

    public function testToManyInheritance(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            [
                'id' => $ids->create('parent'),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'name' => 'test',
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false],
                ],
                'stock' => 10,
                'blogNumber' => $ids->get('parent'),
                'prices' => [
                    [
                        'id' => $ids->create('parent-price'),
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false]],
                        'quantityStart' => 1,
                        'rule' => ['id' => $ids->create('rule'), 'name' => 'any', 'priority' => 1, 'payload' => ''],
                    ],
                ],
            ],
            [
                'id' => $ids->create('variant-1'),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'blogNumber' => $ids->get('variant-1'),
            ],
            [
                'id' => $ids->create('variant-2'),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'blogNumber' => $ids->get('variant-2'),
            ],
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        // test variants should inherit the parent price
        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), null, $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));

        // add price to variant-1, variant-1 should now no more inherited
        $this->blogRepository->update([
            [
                'id' => $ids->get('variant-1'),
                'prices' => [
                    [
                        'id' => $ids->create('variant-1-price'),
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false]],
                        'quantityStart' => 1,
                        'rule' => ['id' => $ids->create('rule'), 'name' => 'any', 'priority' => 1, 'payload' => ''],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), $ids->get('variant-1-price'), $ids->get('variant-1'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));

        // remove parent price
        static::getContainer()->get('blog_price.repository')
            ->delete([
                ['id' => $ids->get('parent-price')],
            ], Context::createDefaultContext());

        $this->assertPriceInheritance($ids->get('parent'), null, $ids->get('parent'), false);
        $this->assertPriceInheritance($ids->get('variant-1'), $ids->get('variant-1-price'), $ids->get('variant-1'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'), false);

        // recreate price and remove variant price

        $this->blogRepository->update([
            [
                'id' => $ids->get('parent'),
                'prices' => [
                    [
                        'id' => $ids->create('parent-price'),
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false]],
                        'quantityStart' => 1,
                        'rule' => ['id' => $ids->create('rule'), 'name' => 'any', 'priority' => 1, 'payload' => ''],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), $ids->get('variant-1-price'), $ids->get('variant-1'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));

        // remove variant price
        static::getContainer()->get('blog_price.repository')
            ->delete([
                ['id' => $ids->get('variant-1-price')],
            ], Context::createDefaultContext());

        // test variants should inherit the parent price
        $this->assertPriceInheritance($ids->get('parent'), $ids->get('parent-price'), $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-1'), null, $ids->get('parent'));
        $this->assertPriceInheritance($ids->get('variant-2'), null, $ids->get('parent'));
    }

    public function testManyToManyInheritance(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            [
                'id' => $ids->create('parent'),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'name' => 'test',
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 1, 'linked' => false],
                ],
                'stock' => 10,
                'blogNumber' => $ids->get('parent'),
                'categories' => [
                    ['id' => $ids->create('parent-category'), 'name' => 'test'],
                ],
            ],
            [
                'id' => $ids->create('variant-1'),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'blogNumber' => $ids->get('variant-1'),
            ],
            [
                'id' => $ids->create('variant-2'),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'blogNumber' => $ids->get('variant-2'),
            ],
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        // test variants should inherit the parent categories
        $this->assertCategoriesInheritance($ids->get('parent'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-1'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-2'), $ids->get('parent'));

        // test assign own variant category
        $this->blogRepository->update([
            [
                'id' => $ids->get('variant-1'),
                'categories' => [
                    ['id' => $ids->create('variant-1-category'), 'name' => 'test12'],
                ],
            ],
        ], Context::createDefaultContext());

        $this->assertCategoriesInheritance($ids->get('parent'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-1'), $ids->get('variant-1'));
        $this->assertCategoriesInheritance($ids->get('variant-2'), $ids->get('parent'));

        static::getContainer()->get('blog_category.repository')
            ->delete([
                ['blogId' => $ids->get('variant-1'), 'categoryId' => $ids->get('variant-1-category')],
            ], Context::createDefaultContext());

        // test variants should inherit the parent categories
        $this->assertCategoriesInheritance($ids->get('parent'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-1'), $ids->get('parent'));
        $this->assertCategoriesInheritance($ids->get('variant-2'), $ids->get('parent'));
    }

    private function assertManufacturerInheritance(string $id, ?string $fk, ?string $association): void
    {
        $inheritance = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(blog_manufacturer_id)) as fk, LOWER(HEX(manufacturer)) as association FROM blog WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertIsArray($inheritance);
        static::assertSame($fk, $inheritance['fk']);
        static::assertSame($association, $inheritance['association']);

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('manufacturer');

        $blog = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);

        static::assertNotNull($blog);
        static::assertSame($association, $blog->getManufacturerId());
        static::assertNotNull($blog->getManufacturer());
        static::assertSame($association, $blog->getManufacturer()->getId());
    }

    private function assertPriceInheritance(string $id, ?string $fk, ?string $association, bool $hasPrices = true): void
    {
        $inheritance = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(prices)) as association FROM blog WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertIsArray($inheritance);
        static::assertSame($association, $inheritance['association']);

        $prices = $this->connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM blog_price WHERE blog_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        ) ?: null;

        static::assertSame($fk, $prices);

        if (!$hasPrices) {
            return;
        }

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $blog = $this->blogRepository
            ->search($criteria, $context)
            ->getEntities()
            ->get($id);

        static::assertNotNull($blog);
        $prices = $blog->getPrices();
        static::assertNotNull($prices);
        static::assertCount(1, $prices);
        static::assertNotNull($prices->first());
        static::assertSame($association, $prices->first()->getBlogId());
    }

    private function assertCategoriesInheritance(string $id, ?string $association, bool $hasCategories = true): void
    {
        $inheritance = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(categories)) as association FROM blog WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertIsArray($inheritance);
        static::assertSame($association, $inheritance['association']);

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('categories');

        $blog = $this->blogRepository->search($criteria, $context)
            ->getEntities()
            ->get($id);

        static::assertNotNull($blog);

        $count = $hasCategories ? 1 : 0;
        static::assertNotNull($blog->getCategories());
        static::assertCount($count, $blog->getCategories());
    }
}
