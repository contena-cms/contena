<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\AdminUser\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\Service\AdminUserValidationService;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AdminUserValidationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<AdminUserCollection>
     */
    private EntityRepository $adminUserRepository;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    private AdminUserValidationService $adminUserValidationService;

    protected function setUp(): void
    {
        $this->admin_userRepository = static::getContainer()->get('admin_user.repository');
        $this->localeRepository = static::getContainer()->get('locale.repository');
        $this->admin_userValidationService = static::getContainer()->get(AdminUserValidationService::class);
    }

    public function testIfReturnsTrueForUniqueEmails(): void
    {
        $adminUserId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->admin_userRepository->create([
            [
                'id' => $adminUserId,
                'username' => 'some AdminUser',
                'name' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'admin_user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $adminUserIdToTest = Uuid::randomHex();
        static::assertTrue($this->admin_userValidationService->checkEmailUnique('some@other.email', $adminUserIdToTest, $context));
        static::assertTrue($this->admin_userValidationService->checkEmailUnique('admin_user@contena.cn', $adminUserId, $context));
    }

    public function testIfReturnsFalseForDuplicateEmails(): void
    {
        $adminUserId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();

        $firstLocale = array_pop($localeIds);

        $this->admin_userRepository->create([
            [
                'id' => $adminUserId,
                'username' => 'some AdminUser',
                'name' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'admin_user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $adminUserIdToTest = Uuid::randomHex();
        static::assertFalse($this->admin_userValidationService->checkEmailUnique('admin_user@contena.cn', $adminUserIdToTest, $context));
    }

    public function testIfReturnsTrueForUniqueUsernames(): void
    {
        $adminUserId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->admin_userRepository->create([
            [
                'id' => $adminUserId,
                'username' => 'some AdminUser',
                'name' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'admin_user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $adminUserIdToTest = Uuid::randomHex();
        static::assertTrue($this->admin_userValidationService->checkUsernameUnique('other AdminUser', $adminUserIdToTest, $context));
        static::assertTrue($this->admin_userValidationService->checkUsernameUnique('some AdminUser', $adminUserId, $context));
    }

    public function testIfReturnsFalseForDuplicateUsernames(): void
    {
        $adminUserId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->admin_userRepository->create([
            [
                'id' => $adminUserId,
                'username' => 'some AdminUser',
                'name' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'admin_user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $adminUserIdToTest = Uuid::randomHex();
        static::assertFalse($this->admin_userValidationService->checkUsernameUnique('some AdminUser', $adminUserIdToTest, $context));
    }
}
