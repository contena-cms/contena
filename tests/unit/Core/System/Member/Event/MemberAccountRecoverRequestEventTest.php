<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Member\Event\MemberAccountRecoverRequestEvent;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(MemberAccountRecoverRequestEvent::class)]
class MemberAccountRecoverRequestEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $channel = new ChannelEntity();
        $channel->setTranslated(['name' => 'my-channel-name']);

        $event = new MemberAccountRecoverRequestEvent(
            Generator::generateChannelContext(channel: $channel),
            new MemberRecoveryEntity(),
            'my-reset-url'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('resetUrl', $flow->data());
        static::assertArrayHasKey('channelName', $flow->data());
        static::assertSame('my-reset-url', $flow->data()['resetUrl']);
        static::assertSame('my-channel-name', $flow->data()['channelName']);
    }
}
