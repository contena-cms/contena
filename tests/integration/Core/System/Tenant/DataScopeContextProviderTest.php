<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Tenant;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Tenant\DataScopeContextProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DataScopeContextProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testProvidesPlatformContextWithoutTenants(): void
    {
        $contexts = [...static::getContainer()->get(DataScopeContextProvider::class)->getContexts()];

        static::assertCount(1, $contexts);
        static::assertSame(Defaults::PLATFORM_DATA_SCOPE, $contexts[0]->getDataScopeId());
        static::assertTrue($contexts[0]->getDataScope()->isPlatform());
        static::assertFalse($contexts[0]->allowsCrossScopeReads());
    }

    public function testProvidesPlatformBeforeEveryTenant(): void
    {
        $tenantA = $this->createTenant('Context provider A');
        $tenantB = $this->createTenant('Context provider B');
        $tenantC = $this->createTenant('Context provider C');

        $connection = static::getContainer()->get(Connection::class);
        $contexts = [...new DataScopeContextProvider($connection, 2)->getContexts()];

        $platformContext = array_shift($contexts);
        static::assertInstanceOf(Context::class, $platformContext);
        static::assertSame(Defaults::PLATFORM_DATA_SCOPE, $platformContext->getDataScopeId());
        static::assertTrue($platformContext->getDataScope()->isPlatform());
        static::assertFalse($platformContext->allowsCrossScopeReads());

        $tenantIds = array_map(static function (Context $context): string {
            $tenantId = $context->getTenantId();
            \assert($tenantId !== null);

            return $tenantId;
        }, $contexts);
        $expectedTenantIds = [$tenantA->id, $tenantB->id, $tenantC->id];
        $dataScopeIds = array_map(static fn (Context $context): string => $context->getDataScopeId(), $contexts);
        sort($tenantIds);
        sort($expectedTenantIds);
        sort($dataScopeIds);

        static::assertSame($expectedTenantIds, $tenantIds);
        static::assertSame($expectedTenantIds, $dataScopeIds);
        static::assertNotContains(true, array_map(static fn (Context $context): bool => $context->allowsCrossScopeReads(), $contexts));
    }

    public function testDerivesExactScopesFromTheProvidedBaseContext(): void
    {
        $tenant = $this->createTenant('Context provider base');
        $baseContext = Context::createGlobalContext()->createWithVersionId('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $extension = new ArrayEntity(['value' => 'preserved']);
        $baseContext->addExtension('test-extension', $extension);

        $contexts = [...static::getContainer()->get(DataScopeContextProvider::class)->getContexts($baseContext)];

        static::assertCount(2, $contexts);
        static::assertSame(Defaults::PLATFORM_DATA_SCOPE, $contexts[0]->getDataScopeId());
        static::assertSame($tenant->id, $contexts[1]->getDataScopeId());

        foreach ($contexts as $context) {
            static::assertFalse($context->allowsCrossScopeReads());
            static::assertSame($baseContext->getSource(), $context->getSource());
            static::assertSame($baseContext->getVersionId(), $context->getVersionId());
            static::assertSame($extension, $context->getExtension('test-extension'));
        }
    }
}
