<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Routing;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\ApiRequestContextResolver;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Integration\IntegrationCollection;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\Locale\LocaleEntity;
use Contena\Core\System\Tenant\Resolver\TenantResolution;
use Contena\Core\System\Tenant\Resolver\TenantResolverChain;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\TenantIsolationTestTrait;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies the tenant binding of the request contexts:
 * platform users get global access and may switch into a tenant, tenant
 * users are bound to their tenant, and channel sources inherit the tenant
 * of their channel.
 *
 * @internal
 */
class TenantContextResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TenantIsolationTestTrait;

    public function testPlatformUserGetsGlobalAccess(): void
    {
        $userId = $this->createUser(null);

        $context = $this->resolveAdminContext($userId);

        static::assertTrue($context->hasGlobalTenantAccess());
        static::assertNull($context->getTenantId());
    }

    public function testPlatformUserCanSwitchIntoATenant(): void
    {
        $tenantId = $this->seedTenant('resolver-a');
        $userId = $this->createUser(null);

        $context = $this->resolveAdminContext($userId, $tenantId);

        static::assertFalse($context->hasGlobalTenantAccess());
        static::assertSame($tenantId, $context->getTenantId());
    }

    public function testTenantUserIsBoundToTheirTenant(): void
    {
        $tenantId = $this->seedTenant('resolver-b');
        $userId = $this->createUser($tenantId);

        $context = $this->resolveAdminContext($userId, $tenantId);

        static::assertFalse($context->hasGlobalTenantAccess());
        static::assertSame($tenantId, $context->getTenantId());
    }

    public function testTenantUserWithoutTenantHeaderIsRejected(): void
    {
        $tenantId = $this->seedTenant('resolver-b2');
        $userId = $this->createUser($tenantId);

        static::expectExceptionObject(RoutingException::tenantSwitchForbidden());

        $this->resolveAdminContext($userId);
    }

    public function testTenantUserCanNotSwitchIntoAnotherTenant(): void
    {
        $tenantId = $this->seedTenant('resolver-c');
        $otherTenantId = $this->seedTenant('resolver-d');
        $userId = $this->createUser($tenantId);

        static::expectExceptionObject(RoutingException::tenantSwitchForbidden());

        $this->resolveAdminContext($userId, $otherTenantId);
    }

    public function testUserCanSwitchBetweenTheirTenants(): void
    {
        $tenantA = $this->seedTenant('resolver-member-a');
        $tenantB = $this->seedTenant('resolver-member-b');
        $userId = $this->createUser($tenantA);
        $this->addUserMembership($userId, $tenantB);

        static::assertSame($tenantA, $this->resolveAdminContext($userId, $tenantA)->getTenantId());
        static::assertSame($tenantB, $this->resolveAdminContext($userId, $tenantB)->getTenantId());
    }

    public function testChannelSourceInheritsTheTenantOfTheChannel(): void
    {
        $tenantId = $this->seedTenant('resolver-e');
        $channelId = $this->createChannel($tenantId);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $channelId);

        new ApiRequestContextResolver(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(RouteScopeRegistry::class),
        )->resolve($request);

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        static::assertInstanceOf(ChannelApiSource::class, $context->getSource());
        static::assertSame($tenantId, $context->getTenantId());
    }

    public function testPlatformUserDefaultsToTheDomainTenant(): void
    {
        $tenant = $this->seedTenantByCode('resolver-f');
        $userId = $this->createUser(null);

        $context = $this->resolveAdminContext($userId, null, 'resolver-f.contena.cn');

        static::assertFalse($context->hasGlobalTenantAccess());
        static::assertSame($tenant, $context->getTenantId());
    }

    public function testTenantUserOnTheirOwnDomainIsBoundWithoutHeader(): void
    {
        $tenant = $this->seedTenantByCode('resolver-g');
        $userId = $this->createUser($tenant);

        $context = $this->resolveAdminContext($userId, null, 'resolver-g.contena.cn');

        static::assertSame($tenant, $context->getTenantId());
    }

    public function testTenantUserOnAnotherTenantsDomainIsRejected(): void
    {
        $tenant = $this->seedTenantByCode('resolver-h');
        $this->seedTenantByCode('resolver-i');
        $userId = $this->createUser($tenant);

        static::expectExceptionObject(RoutingException::tenantDomainMismatch());

        $this->resolveAdminContext($userId, null, 'resolver-i.contena.cn');
    }

    public function testPlatformIntegrationGetsGlobalAccess(): void
    {
        $accessKey = $this->createIntegration(null);

        $context = $this->resolveIntegrationContext($accessKey);

        static::assertTrue($context->hasGlobalTenantAccess());
        static::assertNull($context->getTenantId());
    }

    public function testTenantIntegrationIsBoundToItsTenant(): void
    {
        $tenantId = $this->seedTenant('resolver-integration-a');
        $accessKey = $this->createIntegration($tenantId);

        $context = $this->resolveIntegrationContext($accessKey, $tenantId);

        static::assertFalse($context->hasGlobalTenantAccess());
        static::assertSame($tenantId, $context->getTenantId());
    }

    public function testTenantIntegrationCanNotSwitchIntoAnotherTenant(): void
    {
        $tenantId = $this->seedTenant('resolver-integration-b');
        $otherTenantId = $this->seedTenant('resolver-integration-c');
        $accessKey = $this->createIntegration($tenantId);

        static::expectExceptionObject(RoutingException::tenantSwitchForbidden());

        $this->resolveIntegrationContext($accessKey, $otherTenantId);
    }

    private function seedTenantByCode(string $code): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('tenant.repository')->create([
            ['id' => $id, 'name' => 'Tenant ' . $code, 'code' => $code, 'status' => true],
        ], Context::createDefaultContext());

        return $id;
    }

    private function createUser(?string $tenantId): string
    {
        $userId = Uuid::randomHex();
        $this->userRepository()->create([[
            'id' => $userId,
            'username' => 'tenant-scope-' . \bin2hex(\random_bytes(4)),
            'password' => 'i am safe',
            'email' => \bin2hex(\random_bytes(4)) . '@example.com',
            'name' => 'Tenant Scope User',
            'active' => true,
            'admin' => false,
            'localeId' => $this->systemLocaleId(),
        ]], Context::createDefaultContext());

        if ($tenantId !== null) {
            $this->addUserMembership($userId, $tenantId);
        }

        return $userId;
    }

    private function addUserMembership(string $userId, string $tenantId): void
    {
        static::getContainer()->get('user_tenant.repository')->create([[
            'userId' => $userId,
            'tenantId' => $tenantId,
            'active' => true,
            'admin' => false,
        ]], $this->createTenantContext($tenantId));
    }

    private function createIntegration(?string $tenantId): string
    {
        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $context = $tenantId === null
            ? Context::createDefaultContext()
            : $this->createTenantContext($tenantId);

        /** @var EntityRepository<IntegrationCollection> $repository */
        $repository = static::getContainer()->get('integration.repository');
        $repository->create([[
            'id' => Uuid::randomHex(),
            'label' => 'Tenant context resolver integration',
            'accessKey' => $accessKey,
            'secretAccessKey' => 'secret-' . \bin2hex(\random_bytes(8)),
        ]], $context);

        return $accessKey;
    }

    private function createChannel(string $tenantId): string
    {
        $repository = static::getContainer()->get('channel.repository');
        $default = $repository->search(new Criteria([TestDefaults::CHANNEL]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $default);

        $channelId = Uuid::randomHex();
        $repository->create([[
            'id' => $channelId,
            'name' => 'Tenant context resolver channel',
            'accessKey' => 'resolver-' . \bin2hex(\random_bytes(8)),
            'typeId' => $default->getTypeId(),
            'languageId' => $default->getLanguageId(),
            'countryId' => $default->getCountryId(),
            'memberGroupId' => $default->getMemberGroupId(),
            'navigationCategoryId' => $default->getNavigationCategoryId(),
            'navigationCategoryVersionId' => $default->getNavigationCategoryVersionId(),
            'languages' => [['id' => $default->getLanguageId()]],
            'countries' => [['id' => $default->getCountryId()]],
        ]], Context::createDefaultContext());

        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE channel SET tenant_id = :tenantId WHERE id = :channelId',
            [
                'tenantId' => Uuid::fromHexToBytes($tenantId),
                'channelId' => Uuid::fromHexToBytes($channelId),
            ],
        );

        return $channelId;
    }

    private function resolveAdminContext(string $userId, ?string $switchTenantId = null, ?string $host = null): Context
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID, $userId);

        if ($switchTenantId !== null) {
            $request->headers->set(PlatformRequest::HEADER_TENANT_ID, $switchTenantId);
        }

        if ($host !== null) {
            $request->headers->set('HOST', $host);
            $resolution = static::getContainer()->get(TenantResolverChain::class)->resolve($request);
            if ($resolution instanceof TenantResolution) {
                $request->attributes->set(PlatformRequest::ATTRIBUTE_RESOLVED_TENANT_ID, $resolution);
            }
        }

        static::getContainer()->get(ApiRequestContextResolver::class)->resolve($request);

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        static::assertInstanceOf(AdminApiSource::class, $context->getSource());

        return $context;
    }

    private function resolveIntegrationContext(string $accessKey, ?string $switchTenantId = null): Context
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ApiRouteScope::ID]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, Uuid::randomHex());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $accessKey);

        if ($switchTenantId !== null) {
            $request->headers->set(PlatformRequest::HEADER_TENANT_ID, $switchTenantId);
        }

        static::getContainer()->get(ApiRequestContextResolver::class)->resolve($request);

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        static::assertInstanceOf(AdminApiSource::class, $context->getSource());

        return $context;
    }

    private function systemLocaleId(): string
    {
        $locale = $this->localeRepository()->search(new Criteria(), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(LocaleEntity::class, $locale);

        return $locale->getId();
    }

    /**
     * @return EntityRepository<UserCollection>
     */
    private function userRepository(): EntityRepository
    {
        return static::getContainer()->get('user.repository');
    }

    /**
     * @return EntityRepository<LocaleCollection>
     */
    private function localeRepository(): EntityRepository
    {
        return static::getContainer()->get('locale.repository');
    }
}
