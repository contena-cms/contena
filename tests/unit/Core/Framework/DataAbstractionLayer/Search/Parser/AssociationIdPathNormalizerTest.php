<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Contena\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Contena\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Contena\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Contena\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\AssociationIdPathNormalizer;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AssociationIdPathNormalizer::class)]
class AssociationIdPathNormalizerTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function testNormalize(string $entity, string $field, string $expected): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductManufacturerDefinition::class,
                ProductVisibilityDefinition::class,
                SalesChannelDefinition::class,
                ProductCategoryDefinition::class,
                CategoryDefinition::class,
                ProductPriceDefinition::class,
                'measurement_display_unit.definition' => self::attributeDefinition(MeasurementDisplayUnitEntity::class),
                'measurement_system_code_reference.definition' => self::attributeDefinition(MeasurementSystemCodeReferenceEntity::class),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        static::assertSame($expected, AssociationIdPathNormalizer::normalize($registry->getByEntityName($entity), $field));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function pathProvider(): iterable
    {
        yield 'many to one id' => ['product', 'manufacturer.id', 'manufacturerId'];
        yield 'many to one id with root prefix' => ['product', 'product.manufacturer.id', 'product.manufacturerId'];
        yield 'nested many to one id' => ['product', 'visibilities.salesChannel.id', 'visibilities.salesChannelId'];
        yield 'own id' => ['product', 'id', 'id'];
        yield 'own id with root prefix' => ['product', 'product.id', 'product.id'];
        yield 'non id field' => ['product', 'manufacturer.name', 'manufacturer.name'];
        yield 'many to many id' => ['product', 'categories.id', 'categories.id'];
        yield 'unknown association' => ['product', 'foo.id', 'foo.id'];
        yield 'reverse inherited price product' => ['product_price', 'product.id', 'product.id'];
        yield 'reverse inherited cross selling product' => ['product_cross_selling', 'product.id', 'product.id'];
        yield 'price rule' => ['product_price', 'rule.id', 'ruleId'];
        yield 'nested reverse inherited' => ['product', 'prices.product.id', 'prices.product.id'];
        yield 'attribute entity many to one id' => ['measurement_display_unit', 'measurementSystem.id', 'measurementSystemId'];
        yield 'attribute entity many to one with custom reference' => ['measurement_system_code_reference', 'measurementSystem.id', 'measurementSystem.id'];
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    private static function attributeDefinition(string $entityClass): AttributeEntityDefinition
    {
        foreach ((new AttributeEntityCompiler())->compile($entityClass) as $meta) {
            if ($meta['type'] === 'entity') {
                return new AttributeEntityDefinition($meta);
            }
        }

        static::fail('No entity definition compiled for ' . $entityClass);
    }
}
