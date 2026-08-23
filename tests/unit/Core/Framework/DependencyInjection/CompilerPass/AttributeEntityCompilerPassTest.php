<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Contena\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Contena\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass;
use Contena\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(AttributeEntityCompilerPass::class)]
class AttributeEntityCompilerPassTest extends TestCase
{
    public function testAttributeEntityDefinitionHasTag(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DefinitionInstanceRegistry::class, new Definition(DefinitionInstanceRegistry::class));

        $attributeEntity = new Definition(TestAttributeEntity::class);
        $attributeEntity->setPublic(true);
        $attributeEntity->addTag('contena.entity');
        $container->setDefinition(TestAttributeEntity::class, $attributeEntity);

        $compiler = new AttributeEntityCompiler();

        $compilerPass = new AttributeEntityCompilerPass($compiler);
        $compilerPass->process($container);

        static::assertTrue($container->hasDefinition('test_attribute_entity.definition'));
        static::assertTrue($container->getDefinition('test_attribute_entity.definition')->hasTag('contena.entity.definition'));

        static::assertTrue($container->hasDefinition('test_attribute_entity_translation.definition'));
        static::assertTrue($container->getDefinition('test_attribute_entity_translation.definition')->hasTag('contena.entity.definition'));

        static::assertTrue($container->hasDefinition('media_test_attribute_entity.definition'));
        static::assertTrue($container->getDefinition('media_test_attribute_entity.definition')->hasTag('contena.entity.definition'));
    }
}

/**
 * @internal
 */
#[Entity('test_attribute_entity')]
class TestAttributeEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Required]
    #[Field(type: FieldType::STRING, translated: true)]
    public string $name;

    /**
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    /**
     * @var array<string, MediaEntity>|null
     */
    #[ManyToMany(entity: 'media', onDelete: OnDelete::SET_NULL)]
    public ?array $media = null;
}
