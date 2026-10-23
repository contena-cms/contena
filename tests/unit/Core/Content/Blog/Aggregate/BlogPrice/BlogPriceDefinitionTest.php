<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Aggregate\BlogPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceEntity;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(BlogPriceDefinition::class)]
class BlogPriceDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(BlogPriceDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(BlogPriceEntity::class, $definition->getEntityClass());
        static::assertSame(BlogPriceCollection::class, $definition->getCollectionClass());
    }

    public function testQuantityRangeFieldsRequireAMinimumOfOne(): void
    {
        $fields = $this->createDefinition()->getFields();

        $start = $fields->get('quantityStart');
        static::assertInstanceOf(IntField::class, $start);
        static::assertTrue($start->is(Required::class));
        static::assertSame(1, $start->getMinValue());

        $end = $fields->get('quantityEnd');
        static::assertInstanceOf(IntField::class, $end);
        static::assertSame(1, $end->getMinValue());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testQuantityRangeFieldsCarryNoMinimumInTheLegacyMajor(): void
    {
        $fields = $this->createDefinition()->getFields();

        $start = $fields->get('quantityStart');
        static::assertInstanceOf(IntField::class, $start);
        static::assertNull($start->getMinValue());
    }

}
