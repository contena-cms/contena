<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Channel\ChannelUserAddressCollection;
use Contena\Core\System\User\Channel\ChannelUserAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ChannelUserAddressCollection::class)]
class ChannelUserAddressCollectionTest extends TestCase
{
    public function testGetApiAliasReturnsUniqueAlias(): void
    {
        $collection = new ChannelUserAddressCollection();

        static::assertSame('channel_customer_address_collection', $collection->getApiAlias());
    }

    public function testCollectionAcceptsChannelUserAddressEntity(): void
    {
        $entity = new ChannelUserAddressEntity();
        $entity->setId(Uuid::randomHex());

        $collection = new ChannelUserAddressCollection([$entity]);

        static::assertCount(1, $collection);
        static::assertSame($entity, $collection->first());
    }
}
