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
        $adminUserRecovery = new AdminUserRecoveryEntity();
        $adminUserRecovery->setUniqueIdentifier('recovery');
        $adminUserRecovery->setId('recovery-id');
        $context = Context::createDefaultContext();

        $event = new AdminUserRecoveryRequestEvent($adminUserRecovery, 'my-reset-url', $context);

        static::assertSame(AdminUserRecoveryRequestEvent::EVENT_NAME, $event->getName());
        static::assertSame($adminUserRecovery, $event->getUserRecovery());
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
        $adminUser = new AdminUserEntity();
        $adminUser->setEmail('admin@example.com');
        $adminUser->setName('Max');
        $adminUser->setLastName('Mustermann');

        $adminUserRecovery = new AdminUserRecoveryEntity();
        $adminUserRecovery->setUser($adminUser);

        $event = new AdminUserRecoveryRequestEvent($adminUserRecovery, 'my-reset-url', Context::createDefaultContext());
        $event->getMailStruct();

        $adminUser->setEmail('changed@example.com');

        static::assertSame(['admin@example.com' => 'Max Mustermann'], $event->getMailStruct()->getRecipients());
    }

    private static function eventWithUser(): AdminUserRecoveryRequestEvent
    {
        $adminUser = new AdminUserEntity();
        $adminUser->setEmail('admin@example.com');
        $adminUser->setName('Max');
        $adminUser->setLastName('Mustermann');

        $adminUserRecovery = new AdminUserRecoveryEntity();
        $adminUserRecovery->setUser($adminUser);

        return new AdminUserRecoveryRequestEvent($adminUserRecovery, 'my-reset-url', Context::createDefaultContext());
    }
}
