<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Aggregate\MemberAddress;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressEntity;
use Contena\Core\System\Region\RegionEntity;

/**
 * @internal
 */
#[CoversClass(MemberAddressEntity::class)]
class MemberAddressEntityTest extends TestCase
{
    public function testGetSetRegionAndZipcode(): void
    {
        $address = new MemberAddressEntity();
        $region = new RegionEntity();

        $address->setRegionId('region-id');
        $address->setRegion($region);
        $address->setZipcode('');

        static::assertSame('region-id', $address->getRegionId());
        static::assertSame($region, $address->getRegion());
        static::assertNull($address->getZipcode());
    }
}
