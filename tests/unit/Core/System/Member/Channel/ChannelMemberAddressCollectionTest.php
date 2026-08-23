<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\Channel\ChannelMemberAddressCollection;
use Contena\Core\System\Member\Channel\ChannelMemberAddressEntity;

/**
 * @internal
 */
#[CoversClass(ChannelMemberAddressCollection::class)]
class ChannelMemberAddressCollectionTest extends TestCase
{
    public function testGetApiAliasReturnsUniqueAlias(): void
    {
        $collection = new ChannelMemberAddressCollection();

        static::assertSame('channel_member_address_collection', $collection->getApiAlias());
    }

    public function testCollectionAcceptsChannelMemberAddressEntity(): void
    {
        $entity = new ChannelMemberAddressEntity();
        $entity->setId(Uuid::randomHex());

        $collection = new ChannelMemberAddressCollection([$entity]);

        static::assertCount(1, $collection);
        static::assertSame($entity, $collection->first());
    }
}
