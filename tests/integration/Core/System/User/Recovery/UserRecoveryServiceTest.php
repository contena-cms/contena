<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Recovery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Contena\Core\System\User\Recovery\UserRecoveryService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
class UserRecoveryServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const string VALID_EMAIL = UserProvisioner::USER_EMAIL_FALLBACK;

    private UserRecoveryService $userRecoveryService;

    /**
     * @var EntityRepository<UserRecoveryCollection>
     */
    private EntityRepository $userRecoveryRepo;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepo;

    private Context $context;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->userRepo = $container->get('user.repository');
        $this->userRecoveryRepo = $container->get('user_recovery.repository');
        $this->userRecoveryService = $container->get(UserRecoveryService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGenerateUserRecoveryWithNotExistingSalesChannelLanguage(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $this->context->assign([
            'languageIdChain' => [Uuid::randomHex()],
        ]);

        $eventDispatched = false;
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $this->addEventListener($dispatcher, UserRecoveryRequestEvent::EVENT_NAME, static function (UserRecoveryRequestEvent $event) use (&$eventDispatched): void {
            $eventDispatched = true;
        });

        $this->userRecoveryService->generateUserRecovery(self::VALID_EMAIL, $this->context);

        static::assertTrue($eventDispatched);
    }

    public function testGenerateUserRecoveryWithExistingUser(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $userRecovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(UserRecoveryEntity::class, $userRecovery);
    }

    public function testGenerateUserRecoveryWithoutExistingUser(): void
    {
        $this->createRecovery('foo@bar.com');

        $userRecovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertNull($userRecovery);
    }

    #[DataProvider('dataProviderTestCheckHash')]
    public function testCheckHash(\DateInterval $timeInterval, string $hash, bool $expectedResult): void
    {
        $user = $this->userRepo->search(new Criteria(), $this->context)->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $user);

        $createdTime = new \DateTime()->sub($timeInterval);

        $userId = $user->getId();
        $creatData = [
            'createdAt' => $createdTime,
            'hash' => $hash,
            'userId' => $userId,
        ];

        $this->userRecoveryRepo->create([$creatData], $this->context);

        static::assertSame($expectedResult, $this->userRecoveryService->checkHash($hash, $this->context));
    }

    /**
     * @return array<array<int, \DateInterval|string|bool>>
     */
    public static function dataProviderTestCheckHash(): array
    {
        return [
            [
                new \DateInterval('PT0H'),
                Random::getAlphanumericString(32),
                true,
            ],
            [
                new \DateInterval('PT3H'),
                Random::getAlphanumericString(32),
                false,
            ],
            [
                new \DateInterval('PT1H'),
                Random::getAlphanumericString(32),
                true,
            ],
            [
                new \DateInterval('PT1H58M'),
                Random::getAlphanumericString(32),
                true,
            ],
            [
                new \DateInterval('PT2H'),
                Random::getAlphanumericString(32),
                false,
            ],
            [
                new \DateInterval('PT2H1M'),
                Random::getAlphanumericString(32),
                false,
            ],
        ];
    }

    public function testUpdatePassword(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        static::assertInstanceOf(UserRecoveryEntity::class, $recovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->getEntities()->first());

        $hash = $recovery->getHash();

        $user = $this->userRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        $passwordBefore = $user->getPassword();

        $this->userRecoveryService->updatePassword($hash, 'newPassword', $this->context);

        $userAfter = $this->userRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $userAfter);

        $passwordAfter = $userAfter->getPassword();

        static::assertNotSame($passwordBefore, $passwordAfter);
    }

    public function testGetUserByHash(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $criteria = new Criteria();
        $criteria->setLimit(1);

        static::assertInstanceOf(UserRecoveryEntity::class, $recovery = $this->userRecoveryRepo->search(new Criteria(), $this->context)->getEntities()->first());

        $hash = $recovery->getHash();

        $invalid = $this->userRecoveryService->getUserByHash('invalid', $this->context);
        static::assertNull($invalid);

        $valid = $this->userRecoveryService->getUserByHash($hash, $this->context);
        static::assertInstanceOf(UserEntity::class, $valid);
        static::assertSame(self::VALID_EMAIL, $valid->getEmail());
    }

    private function createRecovery(string $email): void
    {
        $this->userRecoveryService->generateUserRecovery(
            $email,
            Context::createDefaultContext()
        );
    }
}
