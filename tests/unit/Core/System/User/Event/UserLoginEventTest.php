<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[CoversClass(UserLoginEvent::class)]
class UserLoginEventTest extends TestCase
{
    public function testRestoreScalarValuesCorrectly(): void
    {
        $event = new UserLoginEvent(
            static::createStub(ChannelContext::class),
            new UserEntity(),
            'context-token'
        );

        $storer = new ScalarValuesStorer();

        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('contextToken', $flow->data());
        static::assertSame('context-token', $flow->data()['contextToken']);
    }
}
