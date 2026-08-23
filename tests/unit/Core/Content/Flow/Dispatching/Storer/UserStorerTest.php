<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\UserStorer;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;

/**
 * @internal
 */
#[CoversClass(UserStorer::class)]
class UserStorerTest extends TestCase
{
    public function testStoresUserAndRecoveryIdentifiersSeparately(): void
    {
        $recovery = new UserRecoveryEntity();
        $recovery->setId('recovery-id');
        $recovery->setUserId('user-id');
        $event = new UserRecoveryRequestEvent($recovery, 'https://example.test/reset', Context::createDefaultContext());
        $provider = static::createStub(UserRecoveryProvider::class);
        $storer = new UserStorer($provider);

        $stored = $storer->store($event, []);

        static::assertSame('user-id', $stored[UserAware::USER_ID]);
        static::assertSame('recovery-id', $stored[UserAware::USER_RECOVERY_ID]);

        $flow = new StorableFlow($event->getName(), $event->getContext(), $stored);
        $storer->restore($flow);
        static::assertSame('user-id', $flow->getData(UserAware::USER_ID));
    }
}
