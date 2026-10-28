<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class ManyToManyIdFieldIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $productPropertyRepository;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        $this->productPropertyRepository = static::getContainer()->get('product_property.repository');
        $this->productRepository = static::getContainer()->get('product.repository');
    }

    public function testPropertyIndexing(): void
    {
        $data = new IdsCollection();

        $this->createProduct($data);

        $product = $this->productRepository
            ->search(new Criteria([$data->get('product')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        $propertyIds = $product->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertNotContains($data->create('yellow'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);

        $this->productPropertyRepository->delete(
            [['productId' => $data->get('product'), 'optionId' => $data->get('red')]],
            Context::createDefaultContext()
        );

        $product = $this->productRepository
            ->search(new Criteria([$data->get('product')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        $propertyIds = $product->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertNotContains($data->get('red'), $propertyIds);
        static::assertNotContains($data->get('yellow'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);

        $this->productPropertyRepository->create(
            [['productId' => $data->get('product'), 'optionId' => $data->get('red')]],
            Context::createDefaultContext()
        );

        $product = $this->productRepository
            ->search(new Criteria([$data->get('product')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        $propertyIds = $product->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertNotContains($data->get('yellow'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);

        $this->productRepository->update(
            [
                [
                    'id' => $data->get('product'),
                    'properties' => [
                        ['id' => $data->get('yellow'), 'name' => 'yellow', 'groupId' => $data->get('product')],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        $product = $this->productRepository
            ->search(new Criteria([$data->get('product')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        $propertyIds = $product->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertContains($data->get('yellow'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);
    }

    public function testResetRelation(): void
    {
        $data = new IdsCollection();

        $this->createProduct($data);

        $product = $this->productRepository
            ->search(new Criteria([$data->get('product')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        // product is created with red and green, assert both ids are inside the many to many id field
        static::assertInstanceOf(ProductEntity::class, $product);
        $propertyIds = $product->getPropertyIds();
        static::assertIsArray($propertyIds);
        static::assertCount(2, $propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);

        // reset relation, the product has now no more properties
        $this->productPropertyRepository->delete([
            ['productId' => $data->get('product'), 'optionId' => $data->get('red')],
            ['productId' => $data->get('product'), 'optionId' => $data->get('green')],
        ], Context::createDefaultContext());

        $product = $this->productRepository
            ->search(new Criteria([$data->get('product')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);

        $propertyIds = $product->getPropertyIds();
        static::assertNull($propertyIds);

        // test re-assignment
        $this->productPropertyRepository->create([
            ['productId' => $data->get('product'), 'optionId' => $data->get('red')],
            ['productId' => $data->get('product'), 'optionId' => $data->get('green')],
        ], Context::createDefaultContext());

        $product = $this->productRepository
            ->search(new Criteria([$data->get('product')]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        $propertyIds = $product->getPropertyIds();
        static::assertIsArray($propertyIds);

        static::assertCount(2, $propertyIds);
        static::assertContains($data->get('red'), $propertyIds);
        static::assertContains($data->get('green'), $propertyIds);
    }

    private function createProduct(IdsCollection $data): void
    {
        $this->productRepository->create(
            [
                [
                    'id' => $data->create('product'),
                    'name' => __FUNCTION__,
                    'productNumber' => $data->get('product'),
                    'tax' => ['name' => 'test', 'taxRate' => 15],
                    'stock' => 10,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                    ],
                    'properties' => [
                        ['id' => $data->create('red'), 'name' => 'red', 'group' => ['id' => $data->get('product'), 'name' => 'color']],
                        ['id' => $data->create('green'), 'name' => 'green', 'groupId' => $data->get('product')],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }
}
