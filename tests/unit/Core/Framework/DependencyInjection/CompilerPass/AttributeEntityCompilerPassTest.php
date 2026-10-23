<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass;
use Contena\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\DependencyInjection\CompilerPass\ChannelEntityCompilerPass;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

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

        static::assertTrue($container->hasDefinition('user_test_attribute_entity.definition'));
        static::assertTrue($container->getDefinition('user_test_attribute_entity.definition')->hasTag('contena.entity.definition'));
    }

    public function testAssociationsKeepResolvingThroughTheDalRegistryOnceTheChannelRegistryIsBuilt(): void
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AttributeEntityCompilerPass(new AttributeEntityCompiler()), PassConfig::TYPE_BEFORE_OPTIMIZATION, 99);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new ChannelEntityCompilerPass());

        $container->setDefinition(DefinitionInstanceRegistry::class, new Definition(DefinitionInstanceRegistry::class, [new Reference('service_container'), [], []]))->setPublic(true);
        $container->setDefinition(ChannelDefinitionInstanceRegistry::class, new Definition(ChannelDefinitionInstanceRegistry::class, ['', new Reference('service_container'), [], []]))->setPublic(true);

        foreach ([EntityReaderInterface::class, VersionManager::class, EntitySearcherInterface::class, EntityAggregatorInterface::class, 'event_dispatcher', EntityLoadedEventFactory::class, DalSearchInstrumentor::class] as $repositoryDependency) {
            $container->register($repositoryDependency)->setSynthetic(true)->setPublic(true);
        }

        $container->setDefinition(UserDefinition::class, new Definition(UserDefinition::class))->addTag('contena.entity.definition');
        $container->setDefinition(TestAttributeEntity::class, new Definition(TestAttributeEntity::class))->setPublic(true)->addTag('contena.entity');

        $container->compile();

        $registry = $container->get(DefinitionInstanceRegistry::class);
        static::assertInstanceOf(DefinitionInstanceRegistry::class, $registry);
        $container->get(ChannelDefinitionInstanceRegistry::class);

        $users = $registry->getByEntityName('test_attribute_entity')->getFields()->get('users');
        static::assertInstanceOf(ManyToManyAssociationField::class, $users);
        static::assertSame($registry->getByEntityName('user'), $users->getToManyReferenceDefinition());
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
     * @var array<string, UserEntity>|null
     */
    #[ManyToMany(entity: 'user', onDelete: OnDelete::SET_NULL)]
    public ?array $users = null;
}
