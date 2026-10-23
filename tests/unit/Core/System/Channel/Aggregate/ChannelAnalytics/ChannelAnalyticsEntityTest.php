<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Aggregate\ChannelAnalytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsEntity;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(ChannelAnalyticsEntity::class)]
class ChannelAnalyticsEntityTest extends TestCase
{
    public function testGetSetTrackingId(): void
    {
        $entity = new ChannelAnalyticsEntity();
        $trackingId = 'test-tracking-id';
        $entity->setTrackingId($trackingId);

        static::assertSame($trackingId, $entity->getTrackingId());
    }

    public function testGetSetActive(): void
    {
        $entity = new ChannelAnalyticsEntity();
        $entity->setActive(true);

        static::assertTrue($entity->isActive());
    }

    public function testGetSetTrackOrders(): void
    {
        $entity = new ChannelAnalyticsEntity();
        $entity->setTrackOrders(true);

        static::assertTrue($entity->isTrackOrders());
    }

    public function testGetSetAnonymizeIp(): void
    {
        $entity = new ChannelAnalyticsEntity();
        $entity->setAnonymizeIp(true);

        static::assertTrue($entity->isAnonymizeIp());
    }

    public function testGetSetChannel(): void
    {
        $entity = new ChannelAnalyticsEntity();
        $channel = new ChannelEntity();
        $entity->setChannel($channel);

        static::assertSame($channel, $entity->getChannel());
    }

    public function testThatChannelCanBeNull(): void
    {
        $entity = new ChannelAnalyticsEntity();
        static::assertNull($entity->getChannel());
    }
}
