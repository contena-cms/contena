<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Event\UserDoubleOptInRegistrationEvent;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(UserDoubleOptInRegistrationEvent::class)]
class UserDoubleOptInRegistrationEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new UserDoubleOptInRegistrationEvent(
            new UserEntity(),
            static::createStub(ChannelContext::class),
            'my-confirm-url'
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
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $user->setId('test-id');

        $event = new UserDoubleOptInRegistrationEvent($user, $context, 'my-confirm-url');

        static::assertSame('my-confirm-url', $event->getConfirmUrl());
        static::assertSame($context, $event->getChannelContext());
        static::assertSame($user, $event->getUser());
        static::assertSame($context->getChannelId(), $event->getChannelId());
        static::assertSame($context->getContext(), $event->getContext());
        static::assertSame('test-id', $event->getUserId());
    }
}
