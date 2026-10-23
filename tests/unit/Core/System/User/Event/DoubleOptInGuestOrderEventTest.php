<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\DoubleOptInGuestOrderEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(DoubleOptInGuestOrderEvent::class)]
class DoubleOptInGuestOrderEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new DoubleOptInGuestOrderEvent(
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
}
