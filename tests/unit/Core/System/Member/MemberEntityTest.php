<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Member\MemberEntity;

/**
 * @internal
 */
#[CoversClass(MemberEntity::class)]
class MemberEntityTest extends TestCase
{
    public function testGetSetChannelAndActiveFields(): void
    {
        $member = new MemberEntity();
        $channel = new ChannelEntity();

        $member->setChannelId('channel-id');
        $member->setChannel($channel);
        $member->setActive(true);

        static::assertSame('channel-id', $member->getChannelId());
        static::assertSame($channel, $member->getChannel());
        static::assertTrue($member->getActive());
    }
}
