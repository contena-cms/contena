<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\SystemConfig;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TenantTestBehaviour;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class SystemConfigLoaderTenantFallbackTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use TenantTestBehaviour;

    private SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemConfig = static::getContainer()->get(SystemConfigService::class);
    }

    public function testTenantConfigurationOverridesAndFallsBackToPlatformConfiguration(): void
    {
        $platform = Context::createDefaultContext();
        $tenantA = $this->createTenantContext($this->createTenant('System config fallback tenant A'));
        $tenantB = $this->createTenantContext($this->createTenant('System config fallback tenant B'));
        $global = Context::createGlobalContext();

        $this->systemConfig->set('tenant.fallback.inherited', 'platform inherited', context: $platform);
        $this->systemConfig->set('tenant.fallback.overridden', 'platform overridden', context: $platform);
        $this->systemConfig->set('tenant.fallback.overridden', 'tenant A override', context: $tenantA);
        $this->systemConfig->set('tenant.fallback.tenantA', 'tenant A only', context: $tenantA);
        $this->systemConfig->set('tenant.fallback.tenantB', 'tenant B only', context: $tenantB);

        static::assertSame('platform inherited', $this->systemConfig->get('tenant.fallback.inherited', context: $platform));
        static::assertSame('platform overridden', $this->systemConfig->get('tenant.fallback.overridden', context: $platform));
        static::assertNull($this->systemConfig->get('tenant.fallback.tenantA', context: $platform));
        static::assertNull($this->systemConfig->get('tenant.fallback.tenantB', context: $platform));

        static::assertSame('platform inherited', $this->systemConfig->get('tenant.fallback.inherited', context: $tenantA));
        static::assertSame('tenant A override', $this->systemConfig->get('tenant.fallback.overridden', context: $tenantA));
        static::assertSame('tenant A only', $this->systemConfig->get('tenant.fallback.tenantA', context: $tenantA));
        static::assertNull($this->systemConfig->get('tenant.fallback.tenantB', context: $tenantA));

        static::assertSame('platform inherited', $this->systemConfig->get('tenant.fallback.inherited', context: $tenantB));
        static::assertSame('platform overridden', $this->systemConfig->get('tenant.fallback.overridden', context: $tenantB));
        static::assertNull($this->systemConfig->get('tenant.fallback.tenantA', context: $tenantB));
        static::assertSame('tenant B only', $this->systemConfig->get('tenant.fallback.tenantB', context: $tenantB));

        static::assertSame('platform inherited', $this->systemConfig->get('tenant.fallback.inherited', context: $global));
        static::assertSame('platform overridden', $this->systemConfig->get('tenant.fallback.overridden', context: $global));
        static::assertNull($this->systemConfig->get('tenant.fallback.tenantA', context: $global));
        static::assertNull($this->systemConfig->get('tenant.fallback.tenantB', context: $global));

        $this->systemConfig->delete('tenant.fallback.overridden', context: $tenantA);

        static::assertSame('platform overridden', $this->systemConfig->get('tenant.fallback.overridden', context: $tenantA));
    }
}
