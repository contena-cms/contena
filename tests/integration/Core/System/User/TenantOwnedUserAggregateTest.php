<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class TenantOwnedUserAggregateTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var array<string, Context>
     */
    private array $contexts;

    private string $tenantA;

    private string $tenantB;

    protected function setUp(): void
    {
        $this->tenantA = $this->createTenant('User aggregate tenant A')->id;
        $this->tenantB = $this->createTenant('User aggregate tenant B')->id;
        $this->contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($this->tenantA),
            'tenant-b' => Context::createTenantContext($this->tenantB),
            'global' => Context::createGlobalContext(),
        ];
    }

    public function testReadAndWriteMatrix(): void
    {
        $ids = [];
        foreach ($this->contexts as $scope => $context) {
            $ids[$scope] = $this->createUserAggregate($scope, $context);
        }

        $expectedCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];

        foreach ($this->contexts as $scope => $context) {
            foreach (['user' => 'user', 'user_access_key' => 'accessKey', 'user_config' => 'config', 'user_recovery' => 'recovery', 'tag' => 'tag'] as $entityName => $idKey) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria(array_column($ids, $idKey)), $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsAnyFilter('userId', array_column($ids, 'user')));
            static::assertCount(
                $expectedCounts[$scope],
                $this->repository('user_tag')->searchIds($criteria, $context)->getIds(),
                'Unexpected user_tag rows for ' . $scope,
            );
        }

        $expectedTenants = [
            'platform' => null,
            'tenant-a' => $this->tenantA,
            'tenant-b' => $this->tenantB,
            'global' => null,
        ];
        foreach ($ids as $scope => $scopeIds) {
            foreach (['user' => ['id', $scopeIds['user']], 'user_access_key' => ['id', $scopeIds['accessKey']], 'user_config' => ['id', $scopeIds['config']], 'user_recovery' => ['id', $scopeIds['recovery']], 'tag' => ['id', $scopeIds['tag']], 'user_tag' => ['user_id', $scopeIds['user']]] as $table => [$idColumn, $id]) {
                $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
                    \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `%s` = :id', $table, $idColumn),
                    ['id' => Uuid::fromHexToBytes($id)],
                );
                static::assertSame($expectedTenants[$scope], $tenantId === false ? null : $tenantId);
            }
        }

        $this->repository('user_access_key')->update([[
            'id' => $ids['tenant-a']['accessKey'],
            'lastUsageAt' => new \DateTimeImmutable(),
        ]], $this->contexts['tenant-a']);
        $this->repository('user_config')->update([[
            'id' => $ids['tenant-a']['config'],
            'value' => ['updated' => true],
        ]], $this->contexts['tenant-a']);
        $this->repository('user_recovery')->update([[
            'id' => $ids['tenant-a']['recovery'],
            'hash' => 'updated-' . Uuid::randomHex(),
        ]], $this->contexts['tenant-a']);

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            $this->assertWriteRejected(
                fn () => $this->repository('user_access_key')->update([[
                    'id' => $ids['tenant-a']['accessKey'],
                    'lastUsageAt' => new \DateTimeImmutable(),
                ]], $this->contexts[$scope]),
                'Expected user_access_key write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('user_config')->delete([['id' => $ids['tenant-a']['config']]], $this->contexts[$scope]),
                'Expected user_config write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('user_recovery')->delete([['id' => $ids['tenant-a']['recovery']]], $this->contexts[$scope]),
                'Expected user_recovery write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('user_tag')->delete([[
                    'userId' => $ids['tenant-a']['user'],
                    'tagId' => $ids['tenant-a']['tag'],
                ]], $this->contexts[$scope]),
                'Expected user_tag write protection for ' . $scope,
            );
        }

        $this->assertWriteRejected(
            fn () => $this->repository('user_config')->create([[
                'id' => Uuid::randomHex(),
                'userId' => $ids['tenant-b']['user'],
                'key' => 'cross-tenant',
                'value' => [],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant child row referencing another tenant user to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->repository('user_config')->create([[
                'id' => Uuid::randomHex(),
                'userId' => $ids['platform']['user'],
                'key' => 'platform-reference',
                'value' => [],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant child row referencing a platform user to be rejected',
        );
    }

    /**
     * @return array{user: string, accessKey: string, config: string, recovery: string, tag: string}
     */
    private function createUserAggregate(string $scope, Context $context): array
    {
        $userId = Uuid::randomHex();
        $accessKeyId = Uuid::randomHex();
        $configId = Uuid::randomHex();
        $recoveryId = Uuid::randomHex();
        $tagId = Uuid::randomHex();
        $businessScope = \str_starts_with($scope, 'tenant-') ? 'tenant' : $scope;

        $localeId = static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(`id`)) FROM `locale` LIMIT 1');
        static::assertIsString($localeId);

        $this->repository('user')->create([[
            'id' => $userId,
            'userCode' => 'user-code-' . $businessScope,
            'username' => 'user-name-' . $businessScope,
            'password' => 'integration-test-password',
            'name' => 'User aggregate ' . $scope,
            'email' => 'user-email-' . $businessScope . '@example.invalid',
            'localeId' => $localeId,
        ]], $context);
        $this->repository('tag')->create([[
            'id' => $tagId,
            'name' => 'User aggregate tag ' . $scope,
        ]], $context);
        $this->repository('user_access_key')->create([[
            'id' => $accessKeyId,
            'userId' => $userId,
            'accessKey' => 'user-' . $accessKeyId,
            'secretAccessKey' => 'secret-' . $accessKeyId,
        ]], $context);
        $this->repository('user_config')->create([[
            'id' => $configId,
            'userId' => $userId,
            'key' => 'tenant-matrix',
            'value' => ['scope' => $scope],
        ]], $context);
        $this->repository('user_recovery')->create([[
            'id' => $recoveryId,
            'userId' => $userId,
            'hash' => 'recovery-' . $recoveryId,
        ]], $context);
        $this->repository('user_tag')->create([[
            'userId' => $userId,
            'tagId' => $tagId,
        ]], $context);

        return [
            'user' => $userId,
            'accessKey' => $accessKeyId,
            'config' => $configId,
            'recovery' => $recoveryId,
            'tag' => $tagId,
        ];
    }

    private function assertWriteRejected(\Closure $write, string $message): void
    {
        try {
            $write();
            static::fail($message);
        } catch (WriteException) {
        }
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entityName): EntityRepository
    {
        $repository = static::getContainer()->get($entityName . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
