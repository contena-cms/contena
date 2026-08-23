<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class SearchConfigLoaderTenantTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTenantConfigurationIsIsolatedAndFallsBackToThePlatformDefault(): void
    {
        $tenantA = $this->createTenant('Search tenant A');
        $tenantB = $this->createTenant('Search tenant B');
        $tenantWithoutOverride = $this->createTenant('Search tenant without override');

        $this->createSearchConfig($tenantA->id, 'tenantAField');
        $this->createSearchConfig($tenantB->id, 'tenantBField');

        $loader = new SearchConfigLoader($this->connection());

        $tenantAConfig = $loader->load($this->createTenantContext($tenantA));
        $tenantBConfig = $loader->load($this->createTenantContext($tenantB));
        $platformConfig = $loader->load($this->createGlobalTenantContext());
        $fallbackConfig = $loader->load($this->createTenantContext($tenantWithoutOverride));

        static::assertSame(['tenantAField'], array_column($tenantAConfig, 'field'));
        static::assertSame(['tenantBField'], array_column($tenantBConfig, 'field'));
        static::assertNotContains('tenantAField', array_column($platformConfig, 'field'));
        static::assertNotContains('tenantBField', array_column($platformConfig, 'field'));
        static::assertSame($platformConfig, $fallbackConfig);
    }

    private function createSearchConfig(string $tenantId, string $field): void
    {
        $configId = Uuid::randomBytes();
        $tenantId = Uuid::fromHexToBytes($tenantId);
        $createdAt = new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection()->insert('blog_search_config', [
            'tenant_id' => $tenantId,
            'id' => $configId,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'and_logic' => 0,
            'min_search_length' => 2,
            'excluded_terms' => '[]',
            'created_at' => $createdAt,
        ]);
        $this->connection()->insert('blog_search_config_field', [
            'tenant_id' => $tenantId,
            'id' => Uuid::randomBytes(),
            'blog_search_config_id' => $configId,
            'field' => $field,
            'tokenize' => 1,
            'searchable' => 1,
            'use_exact_subfield' => 1,
            'ranking' => 500,
            'created_at' => $createdAt,
        ]);
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
