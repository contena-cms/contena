<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\AdminUser\Recovery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\AdminUser\Service\AdminUserProvisioner;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryRequestEvent;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
class AdminUserRecoveryServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const VALID_EMAIL = AdminUserProvisioner::USER_EMAIL_FALLBACK;

    private AdminUserRecoveryService $admin_userRecoveryService;

    /**
     * @var EntityRepository<AdminUserRecoveryCollection>
     */
    private EntityRepository $admin_userRecoveryRepo;

    /**
     * @var EntityRepository<AdminUserCollection>
     */
    private EntityRepository $admin_userRepo;

    private Context $context;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->admin_userRepo = $container->get('admin_user.repository');
        $this->admin_userRecoveryRepo = $container->get('admin_user_recovery.repository');
        $this->admin_userRecoveryService = $container->get(AdminUserRecoveryService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGenerateUserRecoveryWithNotExistingChannelLanguage(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $this->context->assign([
            'languageIdChain' => [Uuid::randomHex()],
        ]);

        $eventDispatched = false;
        EventHookDispatcher::fromContainer(static::getContainer())->on(AdminUserRecoveryRequestEvent::EVENT_NAME, static function (AdminUserRecoveryRequestEvent $event) use (&$eventDispatched): void {
            $eventDispatched = true;
        });

        $this->admin_userRecoveryService->generateUserRecovery(self::VALID_EMAIL, $this->context);

        static::assertTrue($eventDispatched);
    }

    public function testGenerateUserRecoveryWithExistingUser(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $admin_userRecovery = $this->admin_userRecoveryRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(AdminUserRecoveryEntity::class, $admin_userRecovery);
    }

    public function testGenerateUserRecoveryWithoutExistingUser(): void
    {
        $this->createRecovery('foo@bar.com');

        $admin_userRecovery = $this->admin_userRecoveryRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertNull($admin_userRecovery);
    }

    #[DataProvider('dataProviderTestCheckHash')]
    public function testCheckHash(\DateInterval $timeInterval, string $hash, bool $expectedResult): void
    {
        $admin_user = $this->admin_userRepo->search(new Criteria(), $this->context)->getEntities()->first();

        static::assertInstanceOf(AdminUserEntity::class, $admin_user);

        $createdTime = (new \DateTime())->sub($timeInterval);

        $admin_userId = $admin_user->getId();
        $creatData = [
            'createdAt' => $createdTime,
            'hash' => $hash,
            'admin_userId' => $admin_userId,
        ];

        $this->admin_userRecoveryRepo->create([$creatData], $this->context);

        static::assertSame($expectedResult, $this->admin_userRecoveryService->checkHash($hash, $this->context));
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

        static::assertInstanceOf(AdminUserRecoveryEntity::class, $recovery = $this->admin_userRecoveryRepo->search(new Criteria(), $this->context)->getEntities()->first());

        $hash = $recovery->getHash();

        $admin_user = $this->admin_userRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(AdminUserEntity::class, $admin_user);

        $passwordBefore = $admin_user->getPassword();

        $this->admin_userRecoveryService->updatePassword($hash, 'newPassword', $this->context);

        $admin_userAfter = $this->admin_userRepo->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(AdminUserEntity::class, $admin_userAfter);

        $passwordAfter = $admin_userAfter->getPassword();

        static::assertNotSame($passwordBefore, $passwordAfter);
    }

    public function testGetUserByHash(): void
    {
        $this->createRecovery(self::VALID_EMAIL);

        $criteria = new Criteria();
        $criteria->setLimit(1);

        static::assertInstanceOf(AdminUserRecoveryEntity::class, $recovery = $this->admin_userRecoveryRepo->search(new Criteria(), $this->context)->getEntities()->first());

        $hash = $recovery->getHash();

        $invalid = $this->admin_userRecoveryService->getUserByHash('invalid', $this->context);
        static::assertNull($invalid);

        $valid = $this->admin_userRecoveryService->getUserByHash($hash, $this->context);
        static::assertInstanceOf(AdminUserEntity::class, $valid);
        static::assertSame(self::VALID_EMAIL, $valid->getEmail());
    }

    public function testReEvaluateRules(): void
    {
        $validator = new RuleValidator();
        EventHookDispatcher::fromContainer(static::getContainer())->on(AdminUserRecoveryRequestEvent::EVENT_NAME, $validator);

        $this->admin_userRecoveryService->generateUserRecovery(
            self::VALID_EMAIL,
            Context::createDefaultContext()
        );

        static::assertInstanceOf(AdminUserRecoveryRequestEvent::class, $validator->event);
        static::assertNotCount(0, $validator->event->getContext()->getRuleIds());
    }

    private function createRecovery(string $email): void
    {
        $this->admin_userRecoveryService->generateUserRecovery(
            $email,
            Context::createDefaultContext()
        );
    }
}

/**
 * @internal
 */
class RuleValidator extends CallableClass
{
    public ?AdminUserRecoveryRequestEvent $event = null;

    public function __invoke(): void
    {
        $this->event = func_get_arg(0);
    }
}
