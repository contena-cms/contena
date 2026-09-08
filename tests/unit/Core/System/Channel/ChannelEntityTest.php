<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel;

use Contena\Core\System\Channel\ChannelEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ChannelEntity::class)]
class ChannelEntityTest extends TestCase
{
    public function testGetSetMaintenanceIpAllowlist(): void
    {
        $entity = new ChannelEntity();
        static::assertNull($entity->getMaintenanceIpAllowlist());

        $entity->setMaintenanceIpAllowlist(['127.0.0.1', '::1']);

        static::assertSame(['127.0.0.1', '::1'], $entity->getMaintenanceIpAllowlist());
    }
}
