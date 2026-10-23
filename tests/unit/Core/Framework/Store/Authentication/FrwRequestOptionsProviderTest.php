<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Contena\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigEntity;

/**
 * @internal
 */
#[CoversClass(FrwRequestOptionsProvider::class)]
class FrwRequestOptionsProviderTest extends TestCase
{
    public function testGetAuthenticationHeaderReturnsFrwToken(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('admin_user-id'));

        $admin_userConfig = new AdminUserConfigEntity();
        $admin_userConfig->setUniqueIdentifier('admin_user-config-id');
        $admin_userConfig->setValue([
            'frwUserToken' => 'frw-admin_user-token',
        ]);

        $result = new EntitySearchResult(
            AdminUserConfigDefinition::ENTITY_NAME,
            1,
            new AdminUserConfigCollection([$admin_userConfig]),
            null,
            new Criteria(),
            $context
        );

        $admin_userConfigRepositoryMock = static::createMock(EntityRepository::class);
        $admin_userConfigRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $innerOptionsProvider = static::createStub(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $admin_userConfigRepositoryMock
        );

        static::assertSame([
            'X-Contena-Token' => 'frw-admin_user-token',
        ], $frwRequestOptionsProvider->getAuthenticationHeader($context));
    }

    public function testGetAuthenticationHeaderReturnsEmptyArrayIfFrwTokenIsNull(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('admin_user-id'));

        $admin_userConfig = new AdminUserConfigEntity();
        $admin_userConfig->setUniqueIdentifier('admin_user-config-id');
        $admin_userConfig->setValue([]);

        $result = new EntitySearchResult(
            AdminUserConfigDefinition::ENTITY_NAME,
            1,
            new AdminUserConfigCollection([$admin_userConfig]),
            null,
            new Criteria(),
            $context
        );

        $admin_userConfigRepositoryMock = static::createMock(EntityRepository::class);
        $admin_userConfigRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $innerOptionsProvider = static::createStub(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $admin_userConfigRepositoryMock
        );

        static::assertSame([], $frwRequestOptionsProvider->getAuthenticationHeader($context));
    }

    public function testGetAuthenticationHeaderReturnsEmptyArrayIfUserConfigCanNotBeFound(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('admin_user-id'));

        $result = new EntitySearchResult(
            AdminUserConfigDefinition::ENTITY_NAME,
            1,
            new AdminUserConfigCollection(),
            null,
            new Criteria(),
            $context
        );

        $admin_userConfigRepositoryMock = static::createMock(EntityRepository::class);
        $admin_userConfigRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $innerOptionsProvider = static::createStub(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $admin_userConfigRepositoryMock
        );

        static::assertSame([], $frwRequestOptionsProvider->getAuthenticationHeader($context));
    }

    public function testGetAuthenticationHeaderThrowsIfContextIsNoAdminApiSource(): void
    {
        $context = Context::createDefaultContext();

        $admin_userConfigRepositoryMock = static::createMock(EntityRepository::class);
        $admin_userConfigRepositoryMock->expects($this->never())
            ->method('search');

        $innerOptionsProvider = static::createStub(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $admin_userConfigRepositoryMock
        );

        static::expectException(InvalidContextSourceException::class);
        $frwRequestOptionsProvider->getAuthenticationHeader($context);
    }

    public function testGetDefaultQueryParametersDelegatesToInnerProvider(): void
    {
        $context = Context::createDefaultContext();

        $admin_userConfigRepositoryMock = static::createStub(EntityRepository::class);

        $innerOptionsProvider = static::createMock(AbstractStoreRequestOptionsProvider::class);
        $innerOptionsProvider->expects($this->once())
            ->method('getDefaultQueryParameters')
            ->with($context)
            ->willReturn([
                'queries' => 'some-queries',
            ]);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $admin_userConfigRepositoryMock
        );

        $queries = $frwRequestOptionsProvider->getDefaultQueryParameters($context);

        static::assertSame([
            'queries' => 'some-queries',
        ], $queries);
    }
}
