<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductCrossSellingSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Contena\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Contena\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Contena\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection;
use Contena\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Contena\Core\Content\Test\Product\ProductBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ProductCrossSellingSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testOnlySupportsProductCrossSelling(): void
    {
        /** @var EntityRepository<ProductCrossSellingAssignedProductsCollection> $assignedProductsRepository */
        $assignedProductsRepository = static::getContainer()->get('product_cross_selling_assigned_products.repository');

        $serializer = new ProductCrossSellingSerializer($assignedProductsRepository);

        static::assertTrue($serializer->supports(ProductCrossSellingDefinition::ENTITY_NAME), 'should support product cross selling');

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();
            if ($entity !== ProductCrossSellingDefinition::ENTITY_NAME) {
                static::assertFalse(
                    $serializer->supports($definition->getEntityName()),
                    ProductCrossSellingSerializer::class . ' should not support ' . $entity
                );
            }
        }
    }

    public function testCrossSellingSerialize(): void
    {
        $crossSelling = $this->getProductCrossSelling();

        $assignedProductsRepository = static::getContainer()->get('product_cross_selling_assigned_products.repository');
        $productCrossSellingDefinition = static::getContainer()->get(ProductCrossSellingDefinition::class);

        $serializer = new ProductCrossSellingSerializer($assignedProductsRepository);
        $serializer->setRegistry(static::getContainer()->get(SerializerRegistry::class));

        $serialized = iterator_to_array($serializer->serialize(new Config([], [], []), $productCrossSellingDefinition, $crossSelling));

        static::assertNotCount(0, $serialized);

        $assignedProducts = $crossSelling->getAssignedProducts();
        static::assertNotNull($assignedProducts);
        $assignedProducts->sort(static fn (ProductCrossSellingAssignedProductsEntity $a, ProductCrossSellingAssignedProductsEntity $b) => $a->getPosition() <=> $b->getPosition());
        $productsIds = $assignedProducts->map(static fn (ProductCrossSellingAssignedProductsEntity $assignedProductsEntity) => $assignedProductsEntity->getProductId());

        static::assertSame($crossSelling->getId(), $serialized['id']);
        static::assertSame($crossSelling->getProductId(), $serialized['productId']);
        static::assertSame(implode('|', $productsIds), $serialized['assignedProducts']);

        $deserialized = $serializer->deserialize(new Config([], [], []), $productCrossSellingDefinition, $serialized);

        static::assertIsArray($deserialized);
        static::assertSame($crossSelling->getId(), $deserialized['id']);
        static::assertSame($crossSelling->getProductId(), $deserialized['productId']);
        static::assertSame(array_values($productsIds), array_column($deserialized['assignedProducts'], 'productId'));
    }

    private function getProductCrossSelling(): ProductCrossSellingEntity
    {
        $ids = new IdsCollection();

        $data = [
            (new ProductBuilder($ids, 'a'))->price(15, 10)->visibility()->build(),
            (new ProductBuilder($ids, 'b'))->price(15, 10)->visibility()->build(),
            (new ProductBuilder($ids, 'c'))->price(15, 10)->visibility()->build(),
            (new ProductBuilder($ids, 'd'))->price(15, 10)->visibility()->build(),
            (new ProductBuilder($ids, 'e'))->price(15, 10)->visibility()->build(),
        ];

        $productRepository = static::getContainer()->get('product.repository');
        $productRepository->create($data, Context::createDefaultContext());

        $crossSellingId = Uuid::randomHex();

        $crossSelling = [
            'id' => $crossSellingId,
            'productId' => $ids->get('a'),
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test cross selling',
                ],
            ],
            'type' => 'productList',
            'position' => 1,
            'limit' => 500,
            'sortBy' => 'name',
            'sortDirection' => 'ASC',
            'assignedProducts' => [
                ['productId' => $ids->get('b'), 'position' => 0],
                ['productId' => $ids->get('c'), 'position' => 1],
                ['productId' => $ids->get('d'), 'position' => 2],
                ['productId' => $ids->get('e'), 'position' => 3],
            ],
        ];

        /** @var EntityRepository<ProductCrossSellingCollection> $crossSellingRepository */
        $crossSellingRepository = static::getContainer()->get('product_cross_selling.repository');
        $crossSellingRepository->create([$crossSelling], Context::createDefaultContext());

        $criteria = new Criteria([$crossSellingId]);
        $criteria->addAssociation('assignedProducts');

        $crossSellingEntity = $crossSellingRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($crossSellingEntity);

        return $crossSellingEntity;
    }
}
