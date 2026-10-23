<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\AdminUser\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Contena\Core\Framework\Log\Monolog\ExcludeFlowEventHandler;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Maintenance\AdminUser\Service\AdminUserProvisioner;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryRequestEvent;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
class AdminUserRecoveryControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const VALID_EMAIL = AdminUserProvisioner::USER_EMAIL_FALLBACK;

    public function testUpdateUserPassword(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $this->getBrowser()->request(
            'PATCH',
            '/api/_action/admin_user/admin_user-recovery/password',
            [
                'hash' => $this->getHash(),
                'password' => 'NewPassword!',
                'passwordConfirm' => 'NewPassword!',
            ]
        );

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testUpdateUserPasswordWithInvalidHash(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $this->getBrowser()->request(
            'PATCH',
            '/api/_action/admin_user/admin_user-recovery/password',
            [
                'hash' => 'invalid',
                'password' => 'NewPassword!',
                'passwordConfirm' => 'NewPassword!',
            ]
        );

        static::assertSame(400, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testCreateUserRecovery(): void
    {
        $logger = static::getContainer()->get('monolog.logger.business_events');
        $handlers = $logger->getHandlers();
        $logger->setHandlers([
            new ExcludeFlowEventHandler(static::getContainer()->get(DoctrineSQLHandler::class), [
                AdminUserRecoveryRequestEvent::EVENT_NAME,
            ]),
        ]);

        $dispatchedEvent = null;

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            AdminUserRecoveryRequestEvent::EVENT_NAME,
            static function (AdminUserRecoveryRequestEvent $event) use (&$dispatchedEvent): void {
                $dispatchedEvent = $event;
            },
        );
        $this->getBrowser()->request(
            'POST',
            '/api/_action/admin_user/admin_user-recovery',
            [
                'email' => self::VALID_EMAIL,
            ]
        );

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('admin_user.email', self::VALID_EMAIL));

        $admin_userRecovery = static::getContainer()->get('admin_user_recovery.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertNotNull($admin_userRecovery);
        static::assertNotNull($dispatchedEvent);

        // excluded events and its mail events should not be logged
        $originalEvent = $dispatchedEvent->getName();
        $logCriteria = new Criteria();
        $logCriteria->addFilter(new OrFilter([
            new EqualsFilter('message', $originalEvent),
            new EqualsFilter('context.additionalData.eventName', $originalEvent),
        ]));

        $logEntries = static::getContainer()->get('log_entry.repository')->search(
            $logCriteria,
            Context::createDefaultContext()
        )->getEntities();

        static::assertCount(0, $logEntries);

        $this->resetEventDispatcher();
        $logger->setHandlers($handlers);
    }

    private function createRecovery(string $email): void
    {
        static::getContainer()->get(AdminUserRecoveryService::class)->generateUserRecovery(
            $email,
            Context::createDefaultContext()
        );
    }

    private function getHash(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        static::assertInstanceOf(AdminUserRecoveryEntity::class, $recovery = static::getContainer()->get('admin_user_recovery.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->getEntities()->first());

        return $recovery->getHash();
    }
}
