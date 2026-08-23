<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\User\Service\UserValidationService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class UserValidationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    private UserValidationService $userValidationService;

    protected function setUp(): void
    {
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->localeRepository = static::getContainer()->get('locale.repository');
        $this->userValidationService = static::getContainer()->get(UserValidationService::class);
    }

    public function testIfReturnsTrueForUniqueEmails(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'name' => 'first last',
                'localeId' => $firstLocale,
                'email' => 'user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertTrue($this->userValidationService->checkEmailUnique('some@other.email', $userIdToTest, $context));
        static::assertTrue($this->userValidationService->checkEmailUnique('user@contena.cn', $userId, $context));
    }

    public function testIfReturnsFalseForDuplicateEmails(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();

        $firstLocale = array_pop($localeIds);

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'name' => 'first last',
                'localeId' => $firstLocale,
                'email' => 'user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertFalse($this->userValidationService->checkEmailUnique('user@contena.cn', $userIdToTest, $context));
    }

    public function testIfReturnsTrueForUniqueUsernames(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'name' => 'first last',
                'localeId' => $firstLocale,
                'email' => 'user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertTrue($this->userValidationService->checkUsernameUnique('other User', $userIdToTest, $context));
        static::assertTrue($this->userValidationService->checkUsernameUnique('some User', $userId, $context));
    }

    public function testIfReturnsFalseForDuplicateUsernames(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'name' => 'first last',
                'localeId' => $firstLocale,
                'email' => 'user@contena.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertFalse($this->userValidationService->checkUsernameUnique('some User', $userIdToTest, $context));
    }

    public function testUniquenessIsEvaluatedWithinTheWriteTenant(): void
    {
        $tenantA = $this->createTenant('User validation tenant A')->id;
        $tenantB = $this->createTenant('User validation tenant B')->id;
        $tenantAContext = Context::createTenantContext($tenantA);
        $tenantBContext = Context::createTenantContext($tenantB);
        $platformContext = Context::createDefaultContext();
        $localeId = $this->localeRepository->searchIds(new Criteria(), $platformContext)->firstId();
        static::assertIsString($localeId);

        foreach ([$tenantAContext, $tenantBContext] as $index => $context) {
            $this->userRepository->create([[
                'id' => Uuid::randomHex(),
                'username' => 'shared-user',
                'name' => 'Tenant user ' . $index,
                'localeId' => $localeId,
                'email' => 'shared-user@example.com',
                'password' => TestDefaults::HASHED_PASSWORD,
            ]], $context);
        }

        $candidateId = Uuid::randomHex();
        static::assertFalse($this->userValidationService->checkEmailUnique('shared-user@example.com', $candidateId, $tenantAContext));
        static::assertFalse($this->userValidationService->checkUsernameUnique('shared-user', $candidateId, $tenantBContext));
        static::assertTrue($this->userValidationService->checkEmailUnique('shared-user@example.com', $candidateId, Context::createGlobalContext()));
        static::assertTrue($this->userValidationService->checkUsernameUnique('shared-user', $candidateId, Context::createGlobalContext()));

        $this->userRepository->create([[
            'id' => Uuid::randomHex(),
            'username' => 'shared-user',
            'name' => 'Platform user',
            'localeId' => $localeId,
            'email' => 'shared-user@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
        ]], $platformContext);

        static::assertFalse($this->userValidationService->checkEmailUnique('shared-user@example.com', $candidateId, Context::createGlobalContext()));
        static::assertFalse($this->userValidationService->checkUsernameUnique('shared-user', $candidateId, Context::createGlobalContext()));
    }
}
