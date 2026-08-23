<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\FilteredBulkEntityExtension;
use Contena\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemDefinition;
use Contena\Core\System\DataDictionary\DataDictionaryDefinition;
use Contena\Core\System\Tag\TagDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[CoversClass(EntityCompilerPass::class)]
class EntityCompilerPassTest extends TestCase
{
    public function testEntityRepositoryAutowiring(): void
    {
        $container = new ContainerBuilder();

        $container->register(DataDictionaryDefinition::class, DataDictionaryDefinition::class)
            ->addTag('contena.entity.definition');
        $container->register(DataDictionaryItemDefinition::class, DataDictionaryItemDefinition::class)
            ->addTag('contena.entity.definition');
        $this->registerDefinitionRegistry($container);

        new EntityCompilerPass()->process($container);

        static::assertTrue($container->hasAlias(EntityRepository::class . ' $dataDictionaryRepository'));
        static::assertTrue($container->hasAlias(EntityRepository::class . ' $dataDictionaryItemRepository'));
    }

    public function testEntityRepositoryAutowiringForAlreadyDefinedRepository(): void
    {
        $container = new ContainerBuilder();
        $container->register(TagDefinition::class, TagDefinition::class)
            ->addTag('contena.entity.definition');
        $container->register('tag.repository', EntityRepository::class)
            ->addArgument(new Reference(TagDefinition::class));
        $this->registerDefinitionRegistry($container);

        new EntityCompilerPass()->process($container);

        static::assertTrue($container->hasAlias(EntityRepository::class . ' $tagRepository'));
    }

    public function testEntityExtensionGetsAdded(): void
    {
        $container = $this->getContainerBuilder();
        $container->setDefinition(TagEntityExtension::class, new Definition(TagEntityExtension::class))
            ->addTag('contena.entity.extension');

        new EntityCompilerPass()->process($container);

        $methodCalls = $container->getDefinition(TagDefinition::class)->getMethodCalls();
        static::assertCount(2, $methodCalls);
        static::assertSame('addExtension', $methodCalls[1][0]);
        static::assertInstanceOf(Reference::class, $methodCalls[1][1][0]);
        static::assertSame(TagEntityExtension::class, (string) $methodCalls[1][1][0]);
    }

    public function testBulkEntityExtensionGetsAdded(): void
    {
        $container = $this->getContainerBuilder();
        $container->setDefinition(BulkTagExtension::class, new Definition(BulkTagExtension::class))
            ->addTag('contena.bulk.entity.extension');

        new EntityCompilerPass()->process($container);

        $methodCalls = $container->getDefinition(TagDefinition::class)->getMethodCalls();
        static::assertCount(2, $methodCalls);
        static::assertSame('addExtension', $methodCalls[1][0]);
        static::assertInstanceOf(Definition::class, $methodCalls[1][1][0]);
        static::assertSame(FilteredBulkEntityExtension::class, $methodCalls[1][1][0]->getClass());
    }

    public function testAttributeEntityWithoutArgumentsIsIgnored(): void
    {
        $container = new ContainerBuilder();
        $container->register('test_attribute_entity.definition', AttributeEntityDefinition::class)
            ->addTag('contena.entity.definition');
        $this->registerDefinitionRegistry($container);

        new EntityCompilerPass()->process($container);

        static::assertCount(0, $container->getAliases());
    }

    private function getContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(TagDefinition::class, TagDefinition::class)
            ->addTag('contena.entity.definition');
        $this->registerDefinitionRegistry($container);

        return $container;
    }

    private function registerDefinitionRegistry(ContainerBuilder $container): void
    {
        $container->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([])
            ->addArgument([]);
    }
}

/**
 * @internal
 */
class TagEntityExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new StringField('test', 'test')->addFlags(new Runtime()));
    }

    public function getEntityName(): string
    {
        return TagDefinition::ENTITY_NAME;
    }
}

/**
 * @internal
 */
class BulkTagExtension extends BulkEntityExtension
{
    public function collect(): \Generator
    {
        yield TagDefinition::ENTITY_NAME => [
            new StringField('test', 'test')->addFlags(new Runtime()),
        ];
    }
}
