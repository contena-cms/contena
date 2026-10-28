<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\SalesChannelApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Authentication\LocaleProvider;
use Contena\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Contena\Core\Framework\Store\Services\InstanceService;
use Contena\Core\Framework\Store\StoreException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreRequestOptionsProvider::class)]
class StoreRequestOptionsProviderTest extends TestCase
{
    public function testGetAuthenticationHeaderContainsShopSecretIfExists(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getString')
            ->with('core.store.shopSecret')
            ->willReturn('store-secret');

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->once()),
            $systemConfigService,
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource('user-id'))
        );

        static::assertArrayHasKey('X-Contena-Shop-Secret', $authHeaders);
        static::assertSame('store-secret', $authHeaders['X-Contena-Shop-Secret']);
    }

    public function testGetAuthenticationHeaderDoesNotContainsShopSecretIfNotExists(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getString')
            ->with('core.store.shopSecret')
            ->willReturn('');

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->once()),
            $systemConfigService,
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource('user-id'))
        );

        static::assertArrayNotHasKey('X-Contena-Shop-Secret', $authHeaders);
    }

    public function testGetAuthenticationHeaderReturnsUserToken(): void
    {
        $user = (new UserEntity())->assign([
            '_uniqueIdentifier' => 'user-id',
            'id' => 'user-id',
            'storeToken' => 'sbp-token',
        ]);

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection([$user]), $this->once()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource('user-id'))
        );

        static::assertArrayHasKey('X-Contena-Platform-Token', $authHeaders);
        static::assertSame('sbp-token', $authHeaders['X-Contena-Platform-Token']);
    }

    public function testGetAuthenticationHeaderThrowsIfUserIdIsMissingInAdminApiSource(): void
    {
        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->never()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $this->expectExceptionObject(StoreException::invalidContextSourceUser(AdminApiSource::class));

        $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource(null, 'integration-id'))
        );
    }

    public function testGetAuthenticationHeaderReturnsNullIfUserWasNotFound(): void
    {
        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->once()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new AdminApiSource('user-id'))
        );

        static::assertArrayNotHasKey('X-Contena-Platform-Token', $authHeaders);
    }

    public function testGetAuthenticationHeaderReturnsUserTokenInSystemSourceIfAUserHasToken(): void
    {
        $user = (new UserEntity())->assign([
            '_uniqueIdentifier' => 'user-id',
            'id' => 'user-id',
            'storeToken' => 'sbp-token',
        ]);

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection([$user]), $this->once()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new SystemSource())
        );

        static::assertArrayHasKey('X-Contena-Platform-Token', $authHeaders);
        static::assertSame('sbp-token', $authHeaders['X-Contena-Platform-Token']);
    }

    public function testGetAuthenticationHeaderReturnsNullIfNoUserHasATokenSet(): void
    {
        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->once()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $authHeaders = $provider->getAuthenticationHeader(
            Context::createDefaultContext(new SystemSource())
        );

        static::assertArrayNotHasKey('X-Contena-Platform-Token', $authHeaders);
    }

    public function testGetAuthenticationHeaderThrowsIfContextIsNotSystemNorAdminApiSource(): void
    {
        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->never()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $this->expectExceptionObject(StoreException::invalidContextSource(SystemSource::class, SalesChannelApiSource::class));
        $provider->getAuthenticationHeader(
            Context::createDefaultContext(new SalesChannelApiSource('sales-channel-id'))
        );
    }

    public function testGetDefaultQueryParametersReturnsContenaIdAndLicenseDomainFromServices(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->once())
            ->method('getString')
            ->with('core.store.licenseHost')
            ->willReturn('domain.contena.store');

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->never()),
            $systemConfigService,
            new InstanceService('sw-version', 'instance-id'),
            static::createStub(LocaleProvider::class)
        );

        $queries = $provider->getDefaultQueryParameters(Context::createDefaultContext());

        static::assertArrayHasKey('domain', $queries);
        static::assertSame('domain.contena.store', $queries['domain']);

        static::assertArrayHasKey('contenaVersion', $queries);
        static::assertSame('sw-version', $queries['contenaVersion']);
    }

    public function testGetDefaultQueryParametersDelegatesToLocaleProvider(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $localeProvider = $this->createMock(LocaleProvider::class);
        $localeProvider->expects($this->once())
            ->method('getLocaleFromContext')
            ->with($context)
            ->willReturn('locale-from-provider');

        $provider = new StoreRequestOptionsProvider(
            $this->configureUserRepositorySearchMock(new UserCollection(), $this->never()),
            static::createStub(SystemConfigService::class),
            new InstanceService('sw-version', 'instance-id'),
            $localeProvider
        );

        $queries = $provider->getDefaultQueryParameters($context);

        static::assertArrayHasKey('language', $queries);
        static::assertSame('locale-from-provider', $queries['language']);
    }

    /**
     * @return EntityRepository<UserCollection>&MockObject
     */
    private function configureUserRepositorySearchMock(
        UserCollection $collection,
        InvokedCount $invokedCount
    ): EntityRepository&MockObject {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($invokedCount)
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                $collection->count(),
                $collection,
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ));

        return $entityRepository;
    }
}
