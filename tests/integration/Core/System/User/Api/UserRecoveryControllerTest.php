<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Contena\Core\System\User\Recovery\UserRecoveryService;

/**
 * @internal
 */
class UserRecoveryControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use EventDispatcherBehaviour;

    private const string VALID_EMAIL = UserProvisioner::USER_EMAIL_FALLBACK;

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
        $dispatchedEvent = null;

        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
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

        $this->resetEventDispatcher();
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
