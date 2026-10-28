<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressCollection;
use Contena\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelCustomerAddressCollection::class)]
class SalesChannelCustomerAddressCollectionTest extends TestCase
{
    public function testGetApiAliasReturnsUniqueAlias(): void
    {
        $collection = new SalesChannelCustomerAddressCollection();

        static::assertSame('sales_channel_customer_address_collection', $collection->getApiAlias());
    }

    public function testCollectionAcceptsSalesChannelCustomerAddressEntity(): void
    {
        $entity = new SalesChannelCustomerAddressEntity();
        $entity->setId(Uuid::randomHex());

        $collection = new SalesChannelCustomerAddressCollection([$entity]);

        static::assertCount(1, $collection);
        static::assertSame($entity, $collection->first());
    }
}
