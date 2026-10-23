<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\AdminUser\Recovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\AdminUserAware;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryRequestEvent;

/**
 * @internal
 */
#[CoversClass(AdminUserRecoveryRequestEvent::class)]
class AdminUserRecoveryRequestEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new AdminUserRecoveryRequestEvent(
            new AdminUserRecoveryEntity(),
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
        $admin_userRecovery = new AdminUserRecoveryEntity();
        $admin_userRecovery->setUniqueIdentifier('recovery');
        $admin_userRecovery->setId('recovery-id');
        $context = Context::createDefaultContext();

        $event = new AdminUserRecoveryRequestEvent($admin_userRecovery, 'my-reset-url', $context);

        static::assertSame(AdminUserRecoveryRequestEvent::EVENT_NAME, $event->getName());
        static::assertSame($admin_userRecovery, $event->getUserRecovery());
        static::assertSame($context, $event->getContext());
        static::assertSame('my-reset-url', $event->getResetUrl());
        static::assertSame('recovery-id', $event->getUserId());
        static::assertNull($event->getChannelId());
    }

    public function testGetAvailableData(): void
    {
        $data = AdminUserRecoveryRequestEvent::getAvailableData()->toArray();

        static::assertArrayHasKey(AdminUserAware::USER_RECOVERY, $data);
        static::assertSame(AdminUserRecoveryDefinition::class, $data[AdminUserAware::USER_RECOVERY]['entityClass']);
        static::assertArrayHasKey(FlowMailVariables::RESET_URL, $data);
    }

    public function testGetMailStructBuildsTheRecipientFromTheUser(): void
    {
        $event = self::eventWithUser();

        static::assertSame(['admin@example.com' => 'Max Mustermann'], $event->getMailStruct()->getRecipients());
    }

    public function testGetMailStructKeepsTheRecipientBuiltOnTheFirstCall(): void
    {
        $admin_user = new AdminUserEntity();
        $admin_user->setEmail('admin@example.com');
        $admin_user->setFirstName('Max');
        $admin_user->setLastName('Mustermann');

        $admin_userRecovery = new AdminUserRecoveryEntity();
        $admin_userRecovery->setUser($admin_user);

        $event = new AdminUserRecoveryRequestEvent($admin_userRecovery, 'my-reset-url', Context::createDefaultContext());
        $event->getMailStruct();

        $admin_user->setEmail('changed@example.com');

        static::assertSame(['admin@example.com' => 'Max Mustermann'], $event->getMailStruct()->getRecipients());
    }

    private static function eventWithUser(): AdminUserRecoveryRequestEvent
    {
        $admin_user = new AdminUserEntity();
        $admin_user->setEmail('admin@example.com');
        $admin_user->setFirstName('Max');
        $admin_user->setLastName('Mustermann');

        $admin_userRecovery = new AdminUserRecoveryEntity();
        $admin_userRecovery->setUser($admin_user);

        return new AdminUserRecoveryRequestEvent($admin_userRecovery, 'my-reset-url', Context::createDefaultContext());
    }
}
