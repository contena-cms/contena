<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomEntity\Schema;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\System\CustomEntity\Schema\DynamicMappingEntityDefinition;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(DynamicMappingEntityDefinition::class)]
class DynamicMappingEntityDefinitionTest extends TestCase
{
    public function testCreateExposesTheEntityName(): void
    {
        $definition = DynamicMappingEntityDefinition::create('blog', 'language', 'custom_entity_blog_language');

        static::assertSame('custom_entity_blog_language', $definition->getEntityName());
    }

    public function testDefineFieldsBuildsTheMappingFields(): void
    {
        $definition = DynamicMappingEntityDefinition::create('blog', 'language', 'custom_entity_blog_language');

        new StaticDefinitionInstanceRegistry(
            [BlogDefinition::class, LanguageDefinition::class, $definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $fields = $definition->getFields();

        static::assertInstanceOf(FkField::class, $fields->get('blogId'));
        static::assertInstanceOf(FkField::class, $fields->get('languageId'));
        static::assertInstanceOf(ManyToOneAssociationField::class, $fields->get('blog'));
        static::assertInstanceOf(ManyToOneAssociationField::class, $fields->get('language'));
    }
}
