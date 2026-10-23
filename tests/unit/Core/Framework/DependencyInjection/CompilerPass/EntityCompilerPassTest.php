<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\UserDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

        $container->register(UserAddressDefinition::class, UserAddressDefinition::class)
            ->addTag('contena.entity.definition');
        $container->register(UserDefinition::class, UserDefinition::class)
            ->addTag('contena.entity.definition');

        $container->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([
                UserDefinition::ENTITY_NAME => UserDefinition::class,
                UserAddressDefinition::ENTITY_NAME => UserAddressDefinition::class,
            ])
            ->addArgument([
                UserDefinition::ENTITY_NAME => 'user.repository',
                UserAddressDefinition::ENTITY_NAME => 'user_address.repository',
            ]);

        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);

        static::assertTrue($container->hasAlias('Contena\Core\Framework\DataAbstractionLayer\EntityRepository $userRepository'));
        static::assertTrue($container->hasAlias('Contena\Core\Framework\DataAbstractionLayer\EntityRepository $userAddressRepository'));
    }

    public function testEntityRepositoryAutowiringForAlreadyDefinedRepositories(): void
    {
        $container = new ContainerBuilder();

        $container
            ->register(BlogDefinition::class, BlogDefinition::class)
            ->addTag('contena.entity.definition')
        ;

        $container
            ->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([
                BlogDefinition::ENTITY_NAME => BlogDefinition::class,
            ])
            ->addArgument([
                BlogDefinition::ENTITY_NAME => 'blog.repository',
            ])
        ;

        $container
            ->register('blog.repository', EntityRepository::class)
            ->addArgument(new Reference(BlogDefinition::class))
        ;

        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);

        static::assertTrue($container->hasAlias('Contena\Core\Framework\DataAbstractionLayer\EntityRepository $blogRepository'));
    }

    public function testEntityRepositoryAutowiringWithAttributeEntity(): void
    {
        $container = new ContainerBuilder();
        $container
            ->register('test_attribute_entity.definition', AttributeEntityDefinition::class)
            ->addTag('contena.entity.definition')
        ;
        $container
            ->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([
                'test_attribute_entity' => 'test_attribute_entity.definition',
            ])
            ->addArgument([
                'test_attribute_entity' => 'test_attribute_entity.repository',
            ]);

        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);

        static::assertCount(0, $container->getAliases());
    }
}
