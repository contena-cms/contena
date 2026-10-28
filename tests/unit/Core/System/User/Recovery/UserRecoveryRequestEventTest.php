<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Recovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserRecoveryRequestEvent::class)]
class UserRecoveryRequestEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new UserRecoveryRequestEvent(
            new UserRecoveryEntity(),
            'my-reset-url',
            Context::createDefaultContext(),
        );

        $storer = new ScalarValuesStorer();
        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('resetUrl', $flow->data());
        static::assertSame('my-reset-url', $flow->data()['resetUrl']);
    }

    public function testGettersReturnTheConstructorArguments(): void
    {
        $userRecovery = new UserRecoveryEntity();
        $userRecovery->setUniqueIdentifier('recovery');
        $userRecovery->setId('recovery-id');
        $context = Context::createDefaultContext();

        $event = new UserRecoveryRequestEvent($userRecovery, 'my-reset-url', $context);

        static::assertSame(UserRecoveryRequestEvent::EVENT_NAME, $event->getName());
        static::assertSame($userRecovery, $event->getUserRecovery());
        static::assertSame($context, $event->getContext());
        static::assertSame('my-reset-url', $event->getResetUrl());
        static::assertSame('recovery-id', $event->getUserId());
        static::assertNull($event->getSalesChannelId());
    }

    public function testGetAvailableData(): void
    {
        $data = UserRecoveryRequestEvent::getAvailableData()->toArray();

        static::assertArrayHasKey(UserAware::USER_RECOVERY, $data);
        static::assertSame(UserRecoveryDefinition::class, $data[UserAware::USER_RECOVERY]['entityClass']);
        static::assertArrayHasKey(FlowMailVariables::RESET_URL, $data);
    }

    public function testGetMailStructBuildsTheRecipientFromTheUser(): void
    {
        $event = self::eventWithUser();

        static::assertSame(['admin@example.com' => 'Max Mustermann'], $event->getMailStruct()->getRecipients());
    }

    public function testGetMailStructKeepsTheRecipientBuiltOnTheFirstCall(): void
    {
        $user = new UserEntity();
        $user->setEmail('admin@example.com');
        $user->setFirstName('Max');
        $user->setLastName('Mustermann');

        $userRecovery = new UserRecoveryEntity();
        $userRecovery->setUser($user);

        $event = new UserRecoveryRequestEvent($userRecovery, 'my-reset-url', Context::createDefaultContext());
        $event->getMailStruct();

        $user->setEmail('changed@example.com');

        static::assertSame(['admin@example.com' => 'Max Mustermann'], $event->getMailStruct()->getRecipients());
    }

    private static function eventWithUser(): UserRecoveryRequestEvent
    {
        $user = new UserEntity();
        $user->setEmail('admin@example.com');
        $user->setFirstName('Max');
        $user->setLastName('Mustermann');

        $userRecovery = new UserRecoveryEntity();
        $userRecovery->setUser($user);

        return new UserRecoveryRequestEvent($userRecovery, 'my-reset-url', Context::createDefaultContext());
    }
}
