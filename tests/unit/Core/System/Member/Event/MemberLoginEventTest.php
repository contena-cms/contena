<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\MemberEntity;

/**
 * @internal
 */
#[CoversClass(MemberLoginEvent::class)]
class MemberLoginEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new MemberLoginEvent(
            static::createStub(ChannelContext::class),
            new MemberEntity(),
            'context-token',
        );

        $storer = new ScalarValuesStorer();
        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);
        $storer->restore($flow);

        static::assertArrayHasKey('contextToken', $flow->data());
        static::assertSame('context-token', $flow->data()['contextToken']);
    }
}
