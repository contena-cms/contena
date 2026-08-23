<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Event\MemberBeforeLoginEvent;

/**
 * @internal
 */
#[CoversClass(MemberBeforeLoginEvent::class)]
class MemberBeforeLoginEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new MemberBeforeLoginEvent(static::createStub(ChannelContext::class), 'my-email');

        $storer = new ScalarValuesStorer();
        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);
        $storer->restore($flow);

        static::assertArrayHasKey('email', $flow->data());
        static::assertSame('my-email', $flow->data()['email']);
    }
}
