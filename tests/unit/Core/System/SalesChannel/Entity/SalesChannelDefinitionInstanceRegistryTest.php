<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SalesChannel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Contena\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelDefinitionInstanceRegistry::class)]
class SalesChannelDefinitionInstanceRegistryTest extends TestCase
{
    public function testRegister(): void
    {
        $registry = new SalesChannelDefinitionInstanceRegistry(
            'sales_channel_definition.',
            new Container(),
            [],
            []
        );

        $registry->register(new ProductDefinition());

        static::assertInstanceOf(ProductDefinition::class, $registry->get(ProductDefinition::class));
        static::assertTrue($registry->has(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(ProductDefinition::class, $registry->getByEntityName(ProductDefinition::ENTITY_NAME));
        static::assertInstanceOf(ProductDefinition::class, $registry->getByEntityClass(new ProductEntity()));
    }

    public function testItThrowsExceptionWhenSalesChannelRepositoryWasNotFoundByEntityName(): void
    {
        $registry = new SalesChannelDefinitionInstanceRegistry(
            'sales_channel_definition.',
            new Container(),
            [],
            []
        );

        $this->expectException(SalesChannelRepositoryNotFoundException::class);
        $registry->getSalesChannelRepository('fooBar');
    }
}
