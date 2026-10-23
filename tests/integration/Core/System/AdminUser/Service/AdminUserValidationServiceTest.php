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

/**
 * @internal
 */
class AdminUserValidationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<AdminUserCollection>
     */
    private EntityRepository $admin_userRepository;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    private AdminUserValidationService $admin_userValidationService;

    protected function setUp(): void
    {
        $this->admin_userRepository = static::getContainer()->get('admin_user.repository');
        $this->localeRepository = static::getContainer()->get('locale.repository');
        $this->admin_userValidationService = static::getContainer()->get(AdminUserValidationService::class);
    }

    public function testIfReturnsTrueForUniqueEmails(): void
    {
        $admin_userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->admin_userRepository->create([
            [
                'id' => $admin_userId,
                'username' => 'some AdminUser',
                'firstName' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'admin_user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $admin_userIdToTest = Uuid::randomHex();
        static::assertTrue($this->admin_userValidationService->checkEmailUnique('some@other.email', $admin_userIdToTest, $context));
        static::assertTrue($this->admin_userValidationService->checkEmailUnique('admin_user@contena.cn', $admin_userId, $context));
    }

    public function testIfReturnsFalseForDuplicateEmails(): void
    {
        $admin_userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();

        $firstLocale = array_pop($localeIds);

        $this->admin_userRepository->create([
            [
                'id' => $admin_userId,
                'username' => 'some AdminUser',
                'firstName' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'admin_user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $admin_userIdToTest = Uuid::randomHex();
        static::assertFalse($this->admin_userValidationService->checkEmailUnique('admin_user@contena.cn', $admin_userIdToTest, $context));
    }

    public function testIfReturnsTrueForUniqueUsernames(): void
    {
        $admin_userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->admin_userRepository->create([
            [
                'id' => $admin_userId,
                'username' => 'some AdminUser',
                'firstName' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'admin_user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $admin_userIdToTest = Uuid::randomHex();
        static::assertTrue($this->admin_userValidationService->checkUsernameUnique('other AdminUser', $admin_userIdToTest, $context));
        static::assertTrue($this->admin_userValidationService->checkUsernameUnique('some AdminUser', $admin_userId, $context));
    }

    public function testIfReturnsFalseForDuplicateUsernames(): void
    {
        $admin_userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->admin_userRepository->create([
            [
                'id' => $admin_userId,
                'username' => 'some AdminUser',
                'firstName' => 'first',
                'lastName' => 'last',
                'localeId' => $firstLocale,
                'email' => 'admin_user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $admin_userIdToTest = Uuid::randomHex();
        static::assertFalse($this->admin_userValidationService->checkUsernameUnique('some AdminUser', $admin_userIdToTest, $context));
    }
}
