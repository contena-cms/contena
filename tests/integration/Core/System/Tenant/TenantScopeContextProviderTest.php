<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Tenant;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Tenant\TenantScopeContextProvider;

/**
 * @internal
 */
class TenantScopeContextProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testProvidesPlatformContextWithoutTenants(): void
    {
        $contexts = [...static::getContainer()->get(TenantScopeContextProvider::class)->getContexts()];

        static::assertCount(1, $contexts);
        static::assertNull($contexts[0]->getTenantId());
        static::assertFalse($contexts[0]->hasGlobalTenantAccess());
    }

    public function testProvidesPlatformBeforeEveryTenant(): void
    {
        $tenantA = $this->createTenant('Context provider A');
        $tenantB = $this->createTenant('Context provider B');
        $tenantC = $this->createTenant('Context provider C');

        $connection = static::getContainer()->get(Connection::class);
        $contexts = [...new TenantScopeContextProvider($connection, 2)->getContexts()];

        $platformContext = array_shift($contexts);
        static::assertInstanceOf(Context::class, $platformContext);
        static::assertNull($platformContext->getTenantId());
        static::assertFalse($platformContext->hasGlobalTenantAccess());

        $tenantIds = array_map(static function (Context $context): string {
            $tenantId = $context->getTenantId();
            \assert($tenantId !== null);

            return $tenantId;
        }, $contexts);
        $expectedTenantIds = [$tenantA->id, $tenantB->id, $tenantC->id];
        sort($tenantIds);
        sort($expectedTenantIds);

        static::assertSame($expectedTenantIds, $tenantIds);
        static::assertNotContains(true, array_map(static fn (Context $context): bool => $context->hasGlobalTenantAccess(), $contexts));
    }
}
