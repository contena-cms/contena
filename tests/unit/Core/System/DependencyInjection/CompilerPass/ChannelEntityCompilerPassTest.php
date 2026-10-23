<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\FilteredBulkEntityExtension;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\DependencyInjection\CompilerPass\ChannelEntityCompilerPass;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelEntityCompilerPass::class)]
class ChannelEntityCompilerPassTest extends TestCase
{
    public function testExtensionsGetsAdded(): void
    {
        $container = $this->getContainerBuilder();

        $extension = new Definition(BlogEntityExtension::class);
        $extension->setPublic(true);
        $extension->addTag('contena.entity.extension');
        $container->setDefinition(BlogEntityExtension::class, $extension);

        $container->compile();

        $definition = $container->get(BlogDefinition::class);
        $definition->compile(new StaticDefinitionInstanceRegistry([], static::createStub(ValidatorInterface::class), static::createStub(EntityWriteGateway::class)));

        static::assertTrue($definition->getFields()->has('test'));
        static::assertInstanceOf(StringField::class, $definition->getFields()->get('test'));

        $methodCalls = $container->getDefinition('channel_definition.' . BlogDefinition::class)->getMethodCalls();
        static::assertCount(2, $methodCalls);
        static::assertSame('addExtension', $methodCalls[1][0]);
        static::assertInstanceOf(Reference::class, $methodCalls[1][1][0]);
        static::assertSame(BlogEntityExtension::class, (string) $methodCalls[1][1][0]);
    }

    public function testBulky(): void
    {
        $container = $this->getContainerBuilder();

        $extension = new Definition(BulkyBlogExtension::class);
        $extension->setPublic(true);
        $extension->addTag('contena.bulk.entity.extension');
        $container->setDefinition(BulkyBlogExtension::class, $extension);

        $container->compile();

        $definition = $container->get(BlogDefinition::class);
        $definition->compile(new StaticDefinitionInstanceRegistry([], static::createStub(ValidatorInterface::class), static::createStub(EntityWriteGateway::class)));

        static::assertTrue($definition->getFields()->has('test'));
        static::assertInstanceOf(StringField::class, $definition->getFields()->get('test'));

        $methodCalls = $container->getDefinition('channel_definition.' . BlogDefinition::class)->getMethodCalls();
        static::assertCount(2, $methodCalls);
        static::assertSame('addExtension', $methodCalls[1][0]);
        static::assertInstanceOf(Definition::class, $methodCalls[1][1][0]);
        static::assertSame(FilteredBulkEntityExtension::class, $methodCalls[1][1][0]->getClass());
    }

    public function testAttributeEntityExtensionGetsAdded(): void
    {
        $container = $this->getContainerBuilder();

        $attributeDefinition = new Definition(AttributeEntityDefinition::class);
        $attributeDefinition->setPublic(true);
        $attributeDefinition->addTag('contena.entity.definition');
        $attributeDefinition->addArgument([
            'entity_name' => 'test_attribute_entity',
            'fields' => [],
        ]);
        $container->setDefinition('test_attribute_entity.definition', $attributeDefinition);

        $extension = new Definition(AttributeEntityExtension::class);
        $extension->setPublic(true);
        $extension->addTag('contena.entity.extension');
        $container->setDefinition(AttributeEntityExtension::class, $extension);

        $container->compile();

        static::assertTrue($container->has('test_attribute_entity.definition'));
        $definition = $container->get('test_attribute_entity.definition');
        static::assertInstanceOf(AttributeEntityDefinition::class, $definition);

        $definition->compile(new StaticDefinitionInstanceRegistry([], static::createStub(ValidatorInterface::class), static::createStub(EntityWriteGateway::class)));

        static::assertTrue($definition->getFields()->has('blog'));
        static::assertInstanceOf(ManyToOneAssociationField::class, $definition->getFields()->get('blog'));

        $methodCalls = $container->getDefinition('channel_definition.test_attribute_entity.definition')->getMethodCalls();

        static::assertCount(2, $methodCalls);
        static::assertSame('addExtension', $methodCalls[1][0]);
        static::assertInstanceOf(Reference::class, $methodCalls[1][1][0]);
        static::assertSame(AttributeEntityExtension::class, (string) $methodCalls[1][1][0]);
    }

    public function testAttributeEntityExtensiondWithoutAgruments(): void
    {
        $container = $this->getContainerBuilder();

        $attributeDefinition = new Definition(AttributeEntityDefinition::class);
        $attributeDefinition->setPublic(true);
        $attributeDefinition->addTag('contena.entity.definition');
        $container->setDefinition('test_attribute_entity.definition', $attributeDefinition);

        $extension = new Definition(AttributeEntityExtension::class);
        $extension->setPublic(true);
        $extension->addTag('contena.entity.extension');
        $container->setDefinition(AttributeEntityExtension::class, $extension);

        static::expectException(DefinitionNotFoundException::class);
        $container->compile();
    }

    public function getContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new ChannelEntityCompilerPass());
        $definition = new Definition(ChannelDefinitionInstanceRegistry::class);
        $definition->setArguments([[], [], [], []]);

        $container->setDefinition(ChannelDefinitionInstanceRegistry::class, $definition);

        $blogRegular = new Definition(BlogDefinition::class);
        $blogRegular->setPublic(true);
        $blogRegular->addTag('contena.entity.definition');
        $container->setDefinition(BlogDefinition::class, $blogRegular);

        return $container;
    }
}

/**
 * @internal
 */
class BlogEntityExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new StringField('test', 'test'))->addFlags(new Runtime())
        );
    }

    public function getEntityName(): string
    {
        return 'blog';
    }
}

/**
 * @internal
 */
class BulkyBlogExtension extends BulkEntityExtension
{
    public function collect(): \Generator
    {
        yield 'blog' => [
            (new StringField('test', 'test'))->addFlags(new Runtime()),
        ];
    }
}

/**
 * @internal
 */
class AttributeEntityExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class));
    }

    public function getEntityName(): string
    {
        return 'test_attribute_entity';
    }
}
