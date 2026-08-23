<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Event\MemberDoubleOptInRegistrationEvent;
use Contena\Core\System\Member\MemberEntity;

/**
 * @internal
 */
#[CoversClass(MemberDoubleOptInRegistrationEvent::class)]
class MemberDoubleOptInRegistrationEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new MemberDoubleOptInRegistrationEvent(
            new MemberEntity(),
            static::createStub(ChannelContext::class),
            'my-confirm-url',
        );

        $storer = new ScalarValuesStorer();
        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);
        $storer->restore($flow);

        static::assertArrayHasKey('confirmUrl', $flow->data());
        static::assertSame('my-confirm-url', $flow->data()['confirmUrl']);
    }

    public function testCrud(): void
    {
        $context = static::createStub(ChannelContext::class);
        $member = new MemberEntity();
        $member->setId('test-id');

        $event = new MemberDoubleOptInRegistrationEvent($member, $context, 'my-confirm-url');

        static::assertSame('my-confirm-url', $event->getConfirmUrl());
        static::assertSame($context, $event->getChannelContext());
        static::assertSame($member, $event->getMember());
        static::assertSame('test-id', $event->getMemberId());
    }
}
