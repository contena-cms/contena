<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Cookie\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandler;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TenantTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
class CleanupCookieConsentLogTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use TenantTestBehaviour;

    private const NOW = '2026-08-17 12:00:00 UTC';

    private Connection $connection;

    private SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->systemConfig = static::getContainer()->get(SystemConfigService::class);
    }

    public function testRunHonorsTheFourContextWriteScopes(): void
    {
        $platformContext = Context::createDefaultContext();
        $tenantAContext = $this->createTenantContext($this->createTenant('Cookie cleanup tenant A'));
        $tenantBContext = $this->createTenantContext($this->createTenant('Cookie cleanup tenant B'));
        $this->systemConfig->set(CleanupCookieConsentLogTaskHandler::CONFIG_KEY_RETENTION_DAYS, 120, context: $platformContext);

        $platform = $this->createConsentFixtures('platform', $platformContext);
        $tenantA = $this->createConsentFixtures('tenant-a', $tenantAContext);
        $tenantB = $this->createConsentFixtures('tenant-b', $tenantBContext);

        $this->createHandler(Context::createGlobalContext())->run();

        $this->assertConsentWasDeleted($platform['expired']);
        $this->assertConsentExists($platform['recent']);
        $this->assertConsentExists($tenantA['expired']);
        $this->assertConsentExists($tenantB['expired']);

        $secondPlatform = $this->createConsentFixtures('platform-default', $platformContext);
        $this->createHandler($platformContext)->run();

        $this->assertConsentWasDeleted($secondPlatform['expired']);
        $this->assertConsentExists($secondPlatform['recent']);
        $this->assertConsentExists($tenantA['expired']);
        $this->assertConsentExists($tenantB['expired']);

        $this->createHandler($tenantAContext)->run();

        $this->assertConsentWasDeleted($tenantA['expired']);
        $this->assertConsentExists($tenantA['recent']);
        $this->assertConsentExists($tenantB['expired']);

        $this->createHandler($tenantBContext)->run();

        $this->assertConsentWasDeleted($tenantB['expired']);
        $this->assertConsentExists($tenantB['recent']);
    }

    public function testTenantRetentionFallsBackToPlatformAndCanBeDisabled(): void
    {
        $platformContext = Context::createDefaultContext();
        $tenantAContext = $this->createTenantContext($this->createTenant('Cookie fallback tenant A'));
        $tenantBContext = $this->createTenantContext($this->createTenant('Cookie disabled tenant B'));
        $this->systemConfig->set(CleanupCookieConsentLogTaskHandler::CONFIG_KEY_RETENTION_DAYS, 120, context: $platformContext);
        $this->systemConfig->set(CleanupCookieConsentLogTaskHandler::CONFIG_KEY_RETENTION_DAYS, -1, context: $tenantBContext);

        $platform = $this->createConsentFixture('fallback-platform', $platformContext, '-150 days');
        $tenantA = $this->createConsentFixture('fallback-tenant-a', $tenantAContext, '-150 days');
        $tenantB = $this->createConsentFixture('disabled-tenant-b', $tenantBContext, '-500 days');

        $this->createHandler()->run();

        $this->assertConsentWasDeleted($platform);
        $this->assertConsentWasDeleted($tenantA);
        $this->assertConsentExists($tenantB);
    }

    public function testRunKeepsRecentUnreferencedConfigVersions(): void
    {
        $platformContext = Context::createDefaultContext();
        $this->systemConfig->set(CleanupCookieConsentLogTaskHandler::CONFIG_KEY_RETENTION_DAYS, 120, context: $platformContext);
        $versionId = $this->insertConfigVersion('fresh-platform', $platformContext, '-1 day');

        $this->createHandler($platformContext)->run();

        static::assertTrue($this->rowExists('cookie_consent_config_version', $versionId));
    }

    /**
     * @return array{expired: array{log: string, version: string}, recent: array{log: string, version: string}}
     */
    private function createConsentFixtures(string $scope, Context $context): array
    {
        return [
            'expired' => $this->createConsentFixture($scope . '-expired', $context, '-121 days'),
            'recent' => $this->createConsentFixture($scope . '-recent', $context, '-1 day'),
        ];
    }

    /**
     * @return array{log: string, version: string}
     */
    private function createConsentFixture(string $scope, Context $context, string $createdAtModifier): array
    {
        $versionId = $this->insertConfigVersion($scope, $context, $createdAtModifier);
        $logId = Uuid::randomHex();
        $tenantId = $context->getTenantId();

        $this->connection->insert('cookie_consent_log', [
            'id' => Uuid::fromHexToBytes($logId),
            'tenant_id' => $tenantId === null ? null : Uuid::fromHexToBytes($tenantId),
            'channel_id' => Uuid::randomBytes(),
            'language_id' => Uuid::randomBytes(),
            'consent_action' => 'accept_all',
            'accepted_groups' => '[]',
            'config_hash' => $scope,
            'created_at' => new \DateTimeImmutable(self::NOW)->modify($createdAtModifier)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return ['log' => $logId, 'version' => $versionId];
    }

    private function insertConfigVersion(string $scope, Context $context, string $createdAtModifier): string
    {
        $versionId = Uuid::randomHex();
        $tenantId = $context->getTenantId();

        $this->connection->insert('cookie_consent_config_version', [
            'id' => Uuid::fromHexToBytes($versionId),
            'tenant_id' => $tenantId === null ? null : Uuid::fromHexToBytes($tenantId),
            'config_hash' => $scope,
            'channel_id' => Uuid::randomBytes(),
            'language_id' => Uuid::randomBytes(),
            'cookie_groups' => '[]',
            'created_at' => new \DateTimeImmutable(self::NOW)->modify($createdAtModifier)->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $versionId;
    }

    private function createHandler(Context ...$contexts): CleanupCookieConsentLogTaskHandler
    {
        $contextProvider = static::getContainer()->get(TenantScopeContextProvider::class);
        if ($contexts !== []) {
            $contextProvider = static::createStub(TenantScopeContextProvider::class);
            $contextProvider->method('getContexts')->willReturnCallback(
                static function () use ($contexts): \Generator {
                    yield from $contexts;
                },
            );
        }

        return new CleanupCookieConsentLogTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            static::createStub(LoggerInterface::class),
            $this->systemConfig,
            $this->connection,
            new MockClock(self::NOW),
            $contextProvider,
        );
    }

    /**
     * @param array{log: string, version: string} $consent
     */
    private function assertConsentExists(array $consent): void
    {
        static::assertTrue($this->rowExists('cookie_consent_log', $consent['log']));
        static::assertTrue($this->rowExists('cookie_consent_config_version', $consent['version']));
    }

    /**
     * @param array{log: string, version: string} $consent
     */
    private function assertConsentWasDeleted(array $consent): void
    {
        static::assertFalse($this->rowExists('cookie_consent_log', $consent['log']));
        static::assertFalse($this->rowExists('cookie_consent_config_version', $consent['version']));
    }

    private function rowExists(string $table, string $id): bool
    {
        return (bool) $this->connection->fetchOne(
            \sprintf('SELECT 1 FROM `%s` WHERE `id` = :id', $table),
            ['id' => Uuid::fromHexToBytes($id)],
        );
    }
}
