<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Event\UserAccountRecoverRequestEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(UserAccountRecoverRequestEvent::class)]
class UserAccountRecoverRequestEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $channel = new ChannelEntity();
        $channel->setTranslated(['name' => 'my-shop-name']);

        $context = static::createStub(ChannelContext::class);
        $context->method('getChannel')->willReturn($channel);

        $event = new UserAccountRecoverRequestEvent(
            $context,
            new UserRecoveryEntity(),
            'my-reset-url'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('resetUrl', $flow->data());
        static::assertArrayHasKey('shopName', $flow->data());
        static::assertSame('my-reset-url', $flow->data()['resetUrl']);
        static::assertSame('my-shop-name', $flow->data()['shopName']);
    }
}
