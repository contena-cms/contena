<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(BlogDefinition::class)]
class BlogDefinitionTest extends TestCase
{
    public function testSearchFields(): void
    {
        // don't change this list, each additional field will reduce the performance

        $registry = new StaticDefinitionInstanceRegistry(
            [BlogDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class)
        );

        $definition = $registry->getByEntityName('blog');

        $fields = $definition->getFields();

        $searchable = $fields->filterByFlag(SearchRanking::class);

        $keys = $searchable->getKeys();

        // NEVER add an association to this list!!! otherwise, the API query takes too long and shops with many blogs (more than 1000) will fail
        $expected = ['customSearchKeywords', 'blogNumber', 'manufacturerNumber', 'ean', 'name'];

        sort($expected);
        sort($keys);

        static::assertSame($expected, $keys);
    }

    public function testDefaultsMakeANewBlogAPhysicalSellableItem(): void
    {
        $defaults = $this->getDefinition()->getDefaults();

        static::assertSame(BlogDefinition::TYPE_PHYSICAL, $defaults['type']);
        static::assertFalse($defaults['isCloseout']);
        static::assertTrue($defaults['active']);
        static::assertSame(1, $defaults['minPurchase']);
    }

    public function testChildDefaultsOnlyPresetTheType(): void
    {
        static::assertSame(['type' => BlogDefinition::TYPE_PHYSICAL], $this->getDefinition()->getChildDefaults());
    }

    public function testSince(): void
    {
        static::assertSame('6.0.0.0', $this->getDefinition()->since());
    }

    public function testDescribesItsEntity(): void
    {
        $definition = $this->getDefinition();

        static::assertTrue($definition->isInheritanceAware());
        static::assertSame(BlogCollection::class, $definition->getCollectionClass());
        static::assertSame(BlogEntity::class, $definition->getEntityClass());
        static::assertSame(BlogHydrator::class, $definition->getHydratorClass());
    }

    public function testTypeFieldIsRequiredWithoutALegacyStatesField(): void
    {
        $fields = $this->getDefinition()->getFields();

        static::assertNull($fields->get('states'));

        $type = $fields->get('type');
        static::assertNotNull($type);
        static::assertTrue($type->is(Required::class));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyFieldSetKeepsStatesAndAnOptionalType(): void
    {
        $fields = $this->getDefinition()->getFields();

        static::assertNotNull($fields->get('states'));

        $type = $fields->get('type');
        static::assertNotNull($type);
        static::assertFalse($type->is(Required::class));
    }

    private function getDefinition(): BlogDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [BlogDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class)
        );

        $definition = $registry->getByEntityName('blog');
        static::assertInstanceOf(BlogDefinition::class, $definition);

        return $definition;
    }
}
