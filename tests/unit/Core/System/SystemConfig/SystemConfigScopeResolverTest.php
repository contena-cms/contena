<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Contena\Core\System\SystemConfig\SystemConfigScopeResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SystemConfigScopeResolver::class)]
class SystemConfigScopeResolverTest extends TestCase
{
    public function testResolvesScopeWideConfigurationFromContext(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $resolver = new SystemConfigScopeResolver($connection);

        static::assertSame(Defaults::PLATFORM_DATA_SCOPE, $resolver->resolve(null, null));

        $tenantId = Uuid::randomHex();
        static::assertSame($tenantId, $resolver->resolve(null, Context::createTenantContext($tenantId)));
    }

    public function testAcceptsChannelOwnedByTheContextsExactScope(): void
    {
        $tenantId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                static::stringContains('`data_scope_id` = :dataScopeId'),
                [
                    'channelId' => Uuid::fromHexToBytes($channelId),
                    'dataScopeId' => Uuid::fromHexToBytes($tenantId),
                ],
            )
            ->willReturn(1);

        $resolver = new SystemConfigScopeResolver($connection);

        static::assertSame($tenantId, $resolver->resolve($channelId, Context::createTenantContext($tenantId)));
    }

    public function testRejectsChannelOutsideTheContextsExactScope(): void
    {
        $channelId = Uuid::randomHex();
        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        $resolver = new SystemConfigScopeResolver($connection);

        $this->expectExceptionObject(SystemConfigException::dataScopeContextMismatch($channelId));
        $resolver->resolve($channelId, Context::createGlobalContext());
    }
}
