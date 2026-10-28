<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductCrossSellingSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Contena\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;

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

}
