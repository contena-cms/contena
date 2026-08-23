<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Api\OAuth\UserRepository;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\Tenant\Resolver\SubdomainTenantResolver;
use Contena\Core\System\Tenant\Resolver\TenantResolution;
use Contena\Core\System\User\UserCollection;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\TenantIsolationTestTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class TenantDomainLoginTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TenantIsolationTestTrait;

    public function testTenantUserCanLoginOnTheirOwnDomain(): void
    {
        $tenantId = $this->seedTenantByCode('login-own');
        [$username, $password] = $this->createUser($tenantId);

        $user = $this->userRepositoryWithStack($this->requestStack('login-own.contena.cn', $tenantId))
            ->getUserEntityByUserCredentials($username, $password, 'password', $this->clientEntity());

        static::assertNotNull($user);
    }

    public function testTenantUserCanNotLoginOnAnotherTenantsDomain(): void
    {
        $tenantId = $this->seedTenantByCode('login-foreign-a');
        $otherTenantId = $this->seedTenantByCode('login-foreign-b');
        [$username, $password] = $this->createUser($tenantId);

        $user = $this->userRepositoryWithStack($this->requestStack('login-foreign-b.contena.cn', $otherTenantId))
            ->getUserEntityByUserCredentials($username, $password, 'password', $this->clientEntity());

        static::assertNull($user);
    }

    public function testPlatformUserCanLoginOnAnyTenantDomain(): void
    {
        $tenantId = $this->seedTenantByCode('login-platform');
        [$username, $password] = $this->createUser(null);

        $user = $this->userRepositoryWithStack($this->requestStack('login-platform.contena.cn', $tenantId))
            ->getUserEntityByUserCredentials($username, $password, 'password', $this->clientEntity());

        static::assertNotNull($user);
    }

    public function testSameUsernameResolvesWithinPlatformAndTenantDomains(): void
    {
        $tenantA = $this->seedTenantByCode('login-shared-a');
        $tenantB = $this->seedTenantByCode('login-shared-b');
        $username = 'shared-login-user';
        $email = 'shared-login-user@example.com';

        [, $platformPassword] = $this->createUser(null, $username, 'platform-password', $email);
        [, $tenantAPassword] = $this->createUser($tenantA, $username, 'tenant-a-password', $email);
        [, $tenantBPassword] = $this->createUser($tenantB, $username, 'tenant-b-password', $email);

        static::assertNotNull(
            $this->userRepositoryWithStack(new RequestStack())
                ->getUserEntityByUserCredentials($username, $platformPassword, 'password', $this->clientEntity()),
        );
        static::assertNotNull(
            $this->userRepositoryWithStack($this->requestStack('login-shared-a.contena.cn', $tenantA))
                ->getUserEntityByUserCredentials($username, $tenantAPassword, 'password', $this->clientEntity()),
        );
        static::assertNotNull(
            $this->userRepositoryWithStack($this->requestStack('login-shared-b.contena.cn', $tenantB))
                ->getUserEntityByUserCredentials($username, $tenantBPassword, 'password', $this->clientEntity()),
        );
        static::assertNull(
            $this->userRepositoryWithStack($this->requestStack('login-shared-a.contena.cn', $tenantA))
                ->getUserEntityByUserCredentials($username, $tenantBPassword, 'password', $this->clientEntity()),
        );
        static::assertNull(
            $this->userRepositoryWithStack(new RequestStack())
                ->getUserEntityByUserCredentials($username, $tenantAPassword, 'password', $this->clientEntity()),
        );
    }

    private function seedTenantByCode(string $code): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('tenant.repository')->create([
            ['id' => $id, 'name' => 'Tenant ' . $code, 'code' => $code, 'status' => true],
        ], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return array{string, string}
     */
    private function createUser(
        ?string $tenantId,
        ?string $username = null,
        ?string $password = null,
        ?string $email = null,
    ): array {
        $username ??= 'tenant-login-' . \bin2hex(\random_bytes(4));
        $password ??= 'i am safe';
        $email ??= \bin2hex(\random_bytes(4)) . '@example.com';
        $context = $tenantId === null
            ? Context::createDefaultContext()
            : Context::createTenantContext($tenantId);

        $this->userRepository()->create([[
            'id' => Uuid::randomHex(),
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'name' => 'Tenant Login Test',
            'active' => true,
            'admin' => false,
            'localeId' => $this->systemLocaleId(),
        ]], $context);

        return [$username, $password];
    }

    private function systemLocaleId(): string
    {
        $locale = static::getContainer()->get('locale.repository')->search(new Criteria(), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(LocaleEntity::class, $locale);

        return $locale->getId();
    }

    private function userRepositoryWithStack(RequestStack $stack): UserRepository
    {
        return new UserRepository(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(ClockInterface::class),
            $stack,
        );
    }

    private function requestStack(string $host, string $tenantId): RequestStack
    {
        $request = new Request();
        $request->headers->set('HOST', $host);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_RESOLVED_TENANT_ID, new TenantResolution($tenantId, SubdomainTenantResolver::ID));

        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }

    private function clientEntity(): ClientEntityInterface
    {
        return new class implements ClientEntityInterface {
            public function getIdentifier(): string
            {
                return 'administration';
            }

            public function getName(): string
            {
                return 'Administration';
            }

            public function getRedirectUri(): string
            {
                return 'http://localhost';
            }

            public function isConfidential(): bool
            {
                return true;
            }
        };
    }

    /**
     * @return EntityRepository<UserCollection>
     */
    private function userRepository(): EntityRepository
    {
        return static::getContainer()->get('user.repository');
    }
}
