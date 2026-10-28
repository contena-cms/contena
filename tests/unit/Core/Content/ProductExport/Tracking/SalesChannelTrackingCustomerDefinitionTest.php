<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ProductExport\Tracking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerCollection;
use Contena\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerDefinition;
use Contena\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerEntity;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTrackingCustomerDefinition::class)]
class SalesChannelTrackingCustomerDefinitionTest extends TestCase
{
    private SalesChannelTrackingCustomerDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [SalesChannelTrackingCustomerDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        /** @var SalesChannelTrackingCustomerDefinition $definition */
        $definition = $registry->getByEntityName(SalesChannelTrackingCustomerDefinition::ENTITY_NAME);
        $this->definition = $definition;
    }

    public function testEntityName(): void
    {
        static::assertSame('sales_channel_tracking_customer', $this->definition->getEntityName());
    }

    public function testEntityClass(): void
    {
        static::assertSame(SalesChannelTrackingCustomerEntity::class, $this->definition->getEntityClass());
    }

    public function testCollectionClass(): void
    {
        static::assertSame(SalesChannelTrackingCustomerCollection::class, $this->definition->getCollectionClass());
    }

    public function testSince(): void
    {
        static::assertSame('6.7.9.0', $this->definition->since());
    }

    public function testIdFieldIsPrimaryKey(): void
    {
        $field = $this->definition->getFields()->get('id');
        static::assertInstanceOf(IdField::class, $field);
        static::assertTrue($field->is(PrimaryKey::class));
        static::assertTrue($field->is(Required::class));
    }

    public function testCustomerIdField(): void
    {
        $field = $this->definition->getFields()->get('customerId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testSalesChannelIdField(): void
    {
        $field = $this->definition->getFields()->get('salesChannelId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testCustomerAssociation(): void
    {
        $field = $this->definition->getFields()->get('customer');
        static::assertInstanceOf(OneToOneAssociationField::class, $field);
    }

    public function testSalesChannelAssociation(): void
    {
        $field = $this->definition->getFields()->get('salesChannel');
        static::assertInstanceOf(ManyToOneAssociationField::class, $field);
    }
}
