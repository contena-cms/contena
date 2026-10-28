<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Contena\Core\System\User\Recovery\UserRecoveryService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class UserRecoveryControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const VALID_EMAIL = UserProvisioner::USER_EMAIL_FALLBACK;

    public function testUpdateUserPassword(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $this->getBrowser()->request(
            'PATCH',
            '/api/_action/user/user-recovery/password',
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
            '/api/_action/user/user-recovery/password',
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
        ]);

        $dispatchedEvent = null;

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserRecoveryRequestEvent::EVENT_NAME,
            static function (UserRecoveryRequestEvent $event) use (&$dispatchedEvent): void {
                $dispatchedEvent = $event;
            },
        );
        $this->getBrowser()->request(
            'POST',
            '/api/_action/user/user-recovery',
            [
                'email' => self::VALID_EMAIL,
            ]
        );

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('user.email', self::VALID_EMAIL));

        $userRecovery = static::getContainer()->get('user_recovery.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertNotNull($userRecovery);
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
        static::getContainer()->get(UserRecoveryService::class)->generateUserRecovery(
            $email,
            Context::createDefaultContext()
        );
    }

    private function getHash(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        static::assertInstanceOf(UserRecoveryEntity::class, $recovery = static::getContainer()->get('user_recovery.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->getEntities()->first());

        return $recovery->getHash();
    }
}
