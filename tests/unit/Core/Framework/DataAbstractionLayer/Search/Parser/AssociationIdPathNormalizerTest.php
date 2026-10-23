<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\AssociationIdPathNormalizer;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(AssociationIdPathNormalizer::class)]
class AssociationIdPathNormalizerTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function testNormalize(string $entity, string $field, string $expected): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                BlogDefinition::class,
                BlogManufacturerDefinition::class,
                BlogVisibilityDefinition::class,
                ChannelDefinition::class,
                BlogCategoryDefinition::class,
                CategoryDefinition::class,
                BlogPriceDefinition::class,
                BlogCrossSellingDefinition::class,
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
        yield 'many to one id' => ['blog', 'manufacturer.id', 'manufacturerId'];
        yield 'many to one id with root prefix' => ['blog', 'blog.manufacturer.id', 'blog.manufacturerId'];
        yield 'nested many to one id' => ['blog', 'visibilities.channel.id', 'visibilities.channelId'];
        yield 'own id' => ['blog', 'id', 'id'];
        yield 'own id with root prefix' => ['blog', 'blog.id', 'blog.id'];
        yield 'non id field' => ['blog', 'manufacturer.name', 'manufacturer.name'];
        yield 'many to many id' => ['blog', 'categories.id', 'categories.id'];
        yield 'unknown association' => ['blog', 'foo.id', 'foo.id'];
        yield 'reverse inherited price blog' => ['blog_price', 'blog.id', 'blog.id'];
        yield 'reverse inherited cross selling blog' => ['blog_cross_selling', 'blog.id', 'blog.id'];
        yield 'price rule' => ['blog_price', 'rule.id', 'ruleId'];
        yield 'nested reverse inherited' => ['blog', 'prices.blog.id', 'prices.blog.id'];
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
