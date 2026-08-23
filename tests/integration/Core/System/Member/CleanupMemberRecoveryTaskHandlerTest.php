<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TenantTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\CleanupMemberRecoveryTaskHandler;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
class CleanupMemberRecoveryTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use TenantTestBehaviour;

    private const NOW = '2026-08-17 12:00:00 UTC';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testRunHonorsTheFourContextWriteScopes(): void
    {
        $platformContext = Context::createDefaultContext();
        $tenantAContext = $this->createTenantContext($this->createTenant('Member recovery cleanup tenant A'));
        $tenantBContext = $this->createTenantContext($this->createTenant('Member recovery cleanup tenant B'));

        $platform = $this->createRecoveryFixtures('platform', $platformContext);
        $tenantA = $this->createRecoveryFixtures('tenant-a', $tenantAContext);
        $tenantB = $this->createRecoveryFixtures('tenant-b', $tenantBContext);

        $this->createHandler(Context::createGlobalContext())->run();

        $this->assertRecoveryWasDeleted($platform['expired']);
        $this->assertRecoveryExists($platform['recent']);
        $this->assertRecoveryExists($tenantA['expired']);
        $this->assertRecoveryExists($tenantA['recent']);
        $this->assertRecoveryExists($tenantB['expired']);
        $this->assertRecoveryExists($tenantB['recent']);

        $secondPlatform = $this->createRecoveryFixtures('platform-default', $platformContext);
        $this->createHandler($platformContext)->run();

        $this->assertRecoveryWasDeleted($secondPlatform['expired']);
        $this->assertRecoveryExists($secondPlatform['recent']);
        $this->assertRecoveryExists($tenantA['expired']);
        $this->assertRecoveryExists($tenantB['expired']);

        $this->createHandler($tenantAContext)->run();

        $this->assertRecoveryWasDeleted($tenantA['expired']);
        $this->assertRecoveryExists($tenantA['recent']);
        $this->assertRecoveryExists($tenantB['expired']);

        $this->createHandler($tenantBContext)->run();

        $this->assertRecoveryWasDeleted($tenantB['expired']);
        $this->assertRecoveryExists($tenantB['recent']);
    }

    public function testRunStreamsPlatformAndEveryTenantScope(): void
    {
        $platform = $this->createRecoveryFixtures('stream-platform', Context::createDefaultContext());
        $tenantA = $this->createRecoveryFixtures(
            'stream-tenant-a',
            $this->createTenantContext($this->createTenant('Member recovery stream tenant A')),
        );
        $tenantB = $this->createRecoveryFixtures(
            'stream-tenant-b',
            $this->createTenantContext($this->createTenant('Member recovery stream tenant B')),
        );

        $this->createHandler()->run();

        foreach ([$platform, $tenantA, $tenantB] as $fixtures) {
            $this->assertRecoveryWasDeleted($fixtures['expired']);
            $this->assertRecoveryExists($fixtures['recent']);
        }
    }

    /**
     * @return array{expired: string, recent: string}
     */
    private function createRecoveryFixtures(string $scope, Context $context): array
    {
        return [
            'expired' => $this->createRecoveryFixture($scope, 'expired', '-49 hours', $context),
            'recent' => $this->createRecoveryFixture($scope, 'recent', '-47 hours', $context),
        ];
    }

    private function createRecoveryFixture(string $scope, string $age, string $createdAtModifier, Context $context): string
    {
        $recoveryId = Uuid::randomHex();
        $tenantId = $context->getTenantId();
        $createdAt = new \DateTimeImmutable(self::NOW)->modify($createdAtModifier);

        // This adapter only reads recovery columns; the member aggregate integrity is covered separately.
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->connection->insert('member_recovery', [
                'id' => Uuid::fromHexToBytes($recoveryId),
                'tenant_id' => $tenantId === null ? null : Uuid::fromHexToBytes($tenantId),
                'member_id' => Uuid::randomBytes(),
                'hash' => 'recovery-' . $scope . '-' . $age . '-' . $recoveryId,
                'created_at' => $createdAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        return $recoveryId;
    }

    private function createHandler(Context ...$contexts): CleanupMemberRecoveryTaskHandler
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

        return new CleanupMemberRecoveryTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            static::createStub(LoggerInterface::class),
            $this->connection,
            new MockClock(self::NOW),
            $contextProvider,
        );
    }

    private function assertRecoveryExists(string $id): void
    {
        static::assertTrue($this->recoveryExists($id), 'Expected recovery ' . $id . ' to exist');
    }

    private function assertRecoveryWasDeleted(string $id): void
    {
        static::assertFalse($this->recoveryExists($id), 'Expected recovery ' . $id . ' to be deleted');
    }

    private function recoveryExists(string $id): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `member_recovery` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($id)],
        );
    }
}
