<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Event\UserBeforeLoginEvent;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(UserBeforeLoginEvent::class)]
class UserBeforeLoginEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new UserBeforeLoginEvent(
            static::createStub(ChannelContext::class),
            'my-email'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('email', $flow->data());
        static::assertSame('my-email', $flow->data()['email']);
    }
}
