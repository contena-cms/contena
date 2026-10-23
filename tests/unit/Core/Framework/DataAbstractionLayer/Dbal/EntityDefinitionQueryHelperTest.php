<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturerTranslation\BlogManufacturerTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(EntityDefinitionQueryHelper::class)]
class EntityDefinitionQueryHelperTest extends TestCase
{
    #[DataProvider('provideTestGetRoot')]
    public function testGetRoot(string $accessor, ?string $expectedRoot): void
    {
        $definition = $this->getRegistry()->getByEntityName('blog');

        static::assertSame(
            $expectedRoot,
            EntityDefinitionQueryHelper::getRoot($accessor, $definition)
        );
    }

    #[DataProvider('provideTestGetField')]
    public function testGetField(string $fieldName, bool $resolveTranslated, ?Field $expectedField): void
    {
        $definition = $this->getRegistry()->getByEntityName(BlogDefinition::ENTITY_NAME);
        $actualField = EntityDefinitionQueryHelper::getField($fieldName, $definition, BlogDefinition::ENTITY_NAME, $resolveTranslated);

        if ($expectedField === null) {
            static::assertNull($actualField);

            return;
        }

        static::assertNotNull($actualField);
        static::assertSame(
            $expectedField::class,
            $actualField::class
        );

        static::assertSame(
            $expectedField->getPropertyName(),
            $actualField->getPropertyName()
        );
    }

    #[DataProvider('provideGetAssociatedDefinition')]
    public function testGetAssociatedDefinition(string $accessor, string $expectedEntity): void
    {
        $definition = $this->getRegistry()->getByEntityName('blog');

        static::assertSame(
            $expectedEntity,
            EntityDefinitionQueryHelper::getAssociatedDefinition($definition, $accessor)->getEntityName()
        );
    }

    public static function provideTestGetField(): \Generator
    {
        yield 'unknown field' => [
            'unknown.field',
            true,
            null,
        ];

        yield 'non translated field' => [
            'manufacturerNumber',
            true,
            new StringField('manufacturer_number', 'manufacturerNumber'),
        ];

        yield 'resolve translated on non translated field' => [
            'manufacturerNumber',
            false,
            new StringField('manufacturer_number', 'manufacturerNumber'),
        ];

        yield 'int field' => [
            'manufacturerNumber',
            false,
            new StringField('manufacturer_number', 'manufacturerNumber'),
        ];

        yield 'translated field' => [
            'name',
            false,
            new TranslatedField('name'),
        ];

        yield 'resolve translated field' => [
            'name',
            true,
            new StringField('name', 'name'),
        ];

        yield 'association translated field' => [
            'manufacturer.name',
            false,
            new TranslatedField('name'),
        ];

        yield 'resolve association translated field' => [
            'manufacturer.name',
            true,
            new StringField('name', 'name'),
        ];
    }

    public static function provideTestGetRoot(): \Generator
    {
        yield 'no root' => ['name', null];
        yield 'with root' => ['categories.name', 'categories'];
        yield 'nested root' => ['blog.categories.name', 'categories'];
        yield 'invalid root' => ['invalid.name', null];
    }

    public static function provideGetAssociatedDefinition(): \Generator
    {
        yield 'no root' => ['name', 'blog'];
        yield 'with root' => ['categories.name', 'category'];
        yield 'many to many' => ['blog.categories.name', 'category'];
        yield 'many to one' => ['blog.manufacturer.name', 'blog_manufacturer'];
        yield 'nested' => ['blog.categories.translated.customFields.test', 'category'];
    }

    public function testEscapeQuotesAValidIdentifier(): void
    {
        static::assertSame('`blog`', EntityDefinitionQueryHelper::escape('blog'));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideDisallowedIdentifiers(): \Generator
    {
        yield 'backtick' => ['pro`duct'];
        yield 'question mark' => ['pro?duct'];
        yield 'colon' => ['pro:duct'];
        yield 'control character' => ["pro\nduct"];
    }

    #[DataProvider('provideDisallowedIdentifiers')]
    public function testEscapeRejectsDisallowedIdentifierChars(string $identifier): void
    {
        $this->expectExceptionObject(DataAbstractionLayerException::invalidIdentifier($identifier));

        EntityDefinitionQueryHelper::escape($identifier);
    }

    private function getRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                BlogDefinition::class,
                BlogCategoryDefinition::class,
                CategoryTranslationDefinition::class,
                CategoryDefinition::class,
                BlogManufacturerDefinition::class,
                BlogManufacturerTranslationDefinition::class,
                BlogTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}
