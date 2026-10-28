<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityCompilerPass::class)]
class EntityCompilerPassTest extends TestCase
{
    public function testEntityRepositoryAutowiring(): void
    {
        $container = new ContainerBuilder();

        $container->register(CustomerAddressDefinition::class, CustomerAddressDefinition::class)
            ->addTag('contena.entity.definition');
        $container->register(CustomerDefinition::class, CustomerDefinition::class)
            ->addTag('contena.entity.definition');

        $container->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([
                CustomerDefinition::ENTITY_NAME => CustomerDefinition::class,
                CustomerAddressDefinition::ENTITY_NAME => CustomerAddressDefinition::class,
            ])
            ->addArgument([
                CustomerDefinition::ENTITY_NAME => 'customer.repository',
                CustomerAddressDefinition::ENTITY_NAME => 'customer_address.repository',
            ]);

        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);

        static::assertTrue($container->hasAlias('Contena\Core\Framework\DataAbstractionLayer\EntityRepository $customerRepository'));
        static::assertTrue($container->hasAlias('Contena\Core\Framework\DataAbstractionLayer\EntityRepository $customerAddressRepository'));
    }

    public function testEntityRepositoryAutowiringForAlreadyDefinedRepositories(): void
    {
        $container = new ContainerBuilder();

        $container
            ->register(ProductDefinition::class, ProductDefinition::class)
            ->addTag('contena.entity.definition')
        ;

        $container
            ->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([
                ProductDefinition::ENTITY_NAME => ProductDefinition::class,
            ])
            ->addArgument([
                ProductDefinition::ENTITY_NAME => 'product.repository',
            ])
        ;

        $container
            ->register('product.repository', EntityRepository::class)
            ->addArgument(new Reference(ProductDefinition::class))
        ;

        $entityCompilerPass = new EntityCompilerPass();
        $entityCompilerPass->process($container);

        static::assertTrue($container->hasAlias('Contena\Core\Framework\DataAbstractionLayer\EntityRepository $productRepository'));
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
