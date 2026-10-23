<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(CategoryDefinition::class)]
class CategoryDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(CategoryDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(CategoryEntity::class, $definition->getEntityClass());
        static::assertSame(CategoryCollection::class, $definition->getCollectionClass());
        static::assertSame('6.0.0.0', $definition->since());
    }

    public function testDeprecatedCmsPageIdSwitchedFieldIsGone(): void
    {
        static::assertNull($this->createDefinition()->getFields()->get('cmsPageIdSwitched'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedCmsPageIdSwitchedFieldStillExistsInTheLegacyMajor(): void
    {
        $field = $this->createDefinition()->getFields()->get('cmsPageIdSwitched');

        static::assertInstanceOf(BoolField::class, $field);
    }

    private function createDefinition(): CategoryDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertInstanceOf(CategoryDefinition::class, $definition);

        return $definition;
    }
}
