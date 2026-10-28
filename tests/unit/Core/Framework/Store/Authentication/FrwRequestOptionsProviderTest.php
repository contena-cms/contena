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
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigEntity;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(FrwRequestOptionsProvider::class)]
class FrwRequestOptionsProviderTest extends TestCase
{
    public function testGetAuthenticationHeaderReturnsFrwToken(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        $userConfig->setValue([
            'frwUserToken' => 'frw-user-token',
        ]);

        $result = new EntitySearchResult(
            UserConfigDefinition::ENTITY_NAME,
            1,
            new UserConfigCollection([$userConfig]),
            null,
            new Criteria(),
            $context
        );

        $userConfigRepositoryMock = static::createMock(EntityRepository::class);
        $userConfigRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $innerOptionsProvider = static::createStub(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        static::assertSame([
            'X-Contena-Token' => 'frw-user-token',
        ], $frwRequestOptionsProvider->getAuthenticationHeader($context));
    }

    public function testGetAuthenticationHeaderReturnsEmptyArrayIfFrwTokenIsNull(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        $userConfig->setValue([]);

        $result = new EntitySearchResult(
            UserConfigDefinition::ENTITY_NAME,
            1,
            new UserConfigCollection([$userConfig]),
            null,
            new Criteria(),
            $context
        );

        $userConfigRepositoryMock = static::createMock(EntityRepository::class);
        $userConfigRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $innerOptionsProvider = static::createStub(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        static::assertSame([], $frwRequestOptionsProvider->getAuthenticationHeader($context));
    }

    public function testGetAuthenticationHeaderReturnsEmptyArrayIfUserConfigCanNotBeFound(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $result = new EntitySearchResult(
            UserConfigDefinition::ENTITY_NAME,
            1,
            new UserConfigCollection(),
            null,
            new Criteria(),
            $context
        );

        $userConfigRepositoryMock = static::createMock(EntityRepository::class);
        $userConfigRepositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $innerOptionsProvider = static::createStub(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        static::assertSame([], $frwRequestOptionsProvider->getAuthenticationHeader($context));
    }

    public function testGetAuthenticationHeaderThrowsIfContextIsNoAdminApiSource(): void
    {
        $context = Context::createDefaultContext();

        $userConfigRepositoryMock = static::createMock(EntityRepository::class);
        $userConfigRepositoryMock->expects($this->never())
            ->method('search');

        $innerOptionsProvider = static::createStub(AbstractStoreRequestOptionsProvider::class);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        static::expectException(InvalidContextSourceException::class);
        $frwRequestOptionsProvider->getAuthenticationHeader($context);
    }

    public function testGetDefaultQueryParametersDelegatesToInnerProvider(): void
    {
        $context = Context::createDefaultContext();

        $userConfigRepositoryMock = static::createStub(EntityRepository::class);

        $innerOptionsProvider = static::createMock(AbstractStoreRequestOptionsProvider::class);
        $innerOptionsProvider->expects($this->once())
            ->method('getDefaultQueryParameters')
            ->with($context)
            ->willReturn([
                'queries' => 'some-queries',
            ]);

        $frwRequestOptionsProvider = new FrwRequestOptionsProvider(
            $innerOptionsProvider,
            $userConfigRepositoryMock
        );

        $queries = $frwRequestOptionsProvider->getDefaultQueryParameters($context);

        static::assertSame([
            'queries' => 'some-queries',
        ], $queries);
    }
}
