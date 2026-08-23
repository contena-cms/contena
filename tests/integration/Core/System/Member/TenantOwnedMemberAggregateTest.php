<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class TenantOwnedMemberAggregateTest extends TestCase
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
        $this->tenantA = $this->createTenant('Member aggregate tenant A')->id;
        $this->tenantB = $this->createTenant('Member aggregate tenant B')->id;
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
            $ids[$scope] = $this->createMemberAggregate($scope, $context);
        }

        $expectedCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];

        foreach ($this->contexts as $scope => $context) {
            foreach ([
                'member_group' => 'group',
                'member' => 'member',
                'member_address' => 'address',
                'member_recovery' => 'recovery',
            ] as $entityName => $idKey) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria(array_column($ids, $idKey)), $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            $this->assertMappingCount('member_group_translation', 'memberGroupId', array_column($ids, 'group'), $expectedCounts[$scope], $context, $scope);
            $this->assertMappingCount('member_tag', 'memberId', array_column($ids, 'member'), $expectedCounts[$scope], $context, $scope);
            $this->assertMappingCount('member_group_registration_channel', 'memberGroupId', array_column($ids, 'group'), $expectedCounts[$scope], $context, $scope);
        }

        $expectedTenants = [
            'platform' => null,
            'tenant-a' => $this->tenantA,
            'tenant-b' => $this->tenantB,
            'global' => null,
        ];
        foreach ($ids as $scope => $scopeIds) {
            $this->assertStoredTenant('member_group', 'id', $scopeIds['group'], $expectedTenants[$scope]);
            $this->assertStoredTenant('member_group_translation', 'member_group_id', $scopeIds['group'], $expectedTenants[$scope]);
            $this->assertStoredTenant('member', 'id', $scopeIds['member'], $expectedTenants[$scope]);
            $this->assertStoredTenant('member_address', 'id', $scopeIds['address'], $expectedTenants[$scope]);
            $this->assertStoredTenant('member_recovery', 'id', $scopeIds['recovery'], $expectedTenants[$scope]);
            $this->assertStoredTenant('member_tag', 'member_id', $scopeIds['member'], $expectedTenants[$scope]);
            $this->assertStoredTenant('member_group_registration_channel', 'member_group_id', $scopeIds['group'], $expectedTenants[$scope]);
        }

        $this->repository('member_group')->update([[
            'id' => $ids['tenant-a']['group'],
            'registrationActive' => true,
        ]], $this->contexts['tenant-a']);
        $this->repository('member')->update([[
            'id' => $ids['tenant-a']['member'],
            'name' => 'Updated by tenant A',
        ]], $this->contexts['tenant-a']);
        $this->repository('member_address')->update([[
            'id' => $ids['tenant-a']['address'],
            'city' => 'Updated city',
        ]], $this->contexts['tenant-a']);
        $this->repository('member_recovery')->update([[
            'id' => $ids['tenant-a']['recovery'],
            'hash' => 'updated-' . Uuid::randomHex(),
        ]], $this->contexts['tenant-a']);

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            $this->assertWriteRejected(
                fn () => $this->repository('member_group')->update([[
                    'id' => $ids['tenant-a']['group'],
                    'registrationActive' => false,
                ]], $this->contexts[$scope]),
                'Expected member_group write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('member')->update([[
                    'id' => $ids['tenant-a']['member'],
                    'name' => 'Rejected update',
                ]], $this->contexts[$scope]),
                'Expected member write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('member_address')->delete([['id' => $ids['tenant-a']['address']]], $this->contexts[$scope]),
                'Expected member_address write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('member_recovery')->delete([['id' => $ids['tenant-a']['recovery']]], $this->contexts[$scope]),
                'Expected member_recovery write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('member_tag')->delete([[
                    'memberId' => $ids['tenant-a']['member'],
                    'tagId' => $ids['tenant-a']['tag'],
                ]], $this->contexts[$scope]),
                'Expected member_tag write protection for ' . $scope,
            );
            $this->assertWriteRejected(
                fn () => $this->repository('member_group_registration_channel')->delete([[
                    'memberGroupId' => $ids['tenant-a']['group'],
                    'channelId' => $ids['tenant-a']['channel'],
                ]], $this->contexts[$scope]),
                'Expected member_group_registration_channel write protection for ' . $scope,
            );
        }

        $this->assertWriteRejected(
            fn () => $this->repository('member_tag')->create([[
                'memberId' => $ids['tenant-a']['member'],
                'tagId' => $ids['tenant-b']['tag'],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant member tag referencing another tenant tag to be rejected',
        );
        $this->assertWriteRejected(
            fn () => $this->repository('member_tag')->create([[
                'memberId' => $ids['tenant-a']['member'],
                'tagId' => $ids['platform']['tag'],
            ]], $this->contexts['tenant-a']),
            'Expected a tenant member tag referencing a platform tag to be rejected',
        );
    }

    public function testPlatformAdministratorRemainsTheAuditActorForTenantWrites(): void
    {
        $platformUserId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `user` WHERE `tenant_id` IS NULL LIMIT 1',
        );
        static::assertIsString($platformUserId);

        $ids = $this->createMemberAggregate('audit-source', $this->contexts['tenant-a']);
        $channel = $this->repository('channel')->search(
            new Criteria([$ids['channel']]),
            Context::createGlobalContext(),
        )->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);

        $memberId = Uuid::randomHex();
        $source = new AdminApiSource($platformUserId);
        $source->setIsAdmin(true);
        $context = Context::createTenantContext($this->tenantA, $source);
        $context->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($ids, $channel, $memberId): void {
            $this->repository('member')->create([[
                'id' => $memberId,
                'groupId' => $ids['group'],
                'channelId' => $ids['channel'],
                'languageId' => $channel->getLanguageId(),
                'memberNumber' => 'audit-' . \bin2hex(\random_bytes(4)),
                'name' => 'Audited tenant member',
                'email' => 'audit-' . \bin2hex(\random_bytes(4)) . '@example.invalid',
            ]], $context);
            $this->repository('member')->update([[
                'id' => $memberId,
                'name' => 'Updated audited tenant member',
            ]], $context);
        });

        $row = static::getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT LOWER(HEX(`tenant_id`)) AS `tenant_id`, LOWER(HEX(`created_by_id`)) AS `created_by_id`, LOWER(HEX(`updated_by_id`)) AS `updated_by_id` FROM `member` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($memberId)],
        );
        static::assertIsArray($row);
        static::assertSame($this->tenantA, $row['tenant_id']);
        static::assertSame($platformUserId, $row['created_by_id']);
        static::assertSame($platformUserId, $row['updated_by_id']);
    }

    /**
     * @return array{group: string, channel: string, member: string, address: string, recovery: string, tag: string}
     */
    private function createMemberAggregate(string $scope, Context $context): array
    {
        $default = $this->repository('channel')->search(new Criteria([TestDefaults::CHANNEL]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $default);

        $groupId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $memberId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $recoveryId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $this->repository('member_group')->create([[
            'id' => $groupId,
            'name' => 'Member aggregate group ' . $scope,
        ]], $context);
        $this->repository('category')->create([[
            'id' => $categoryId,
            'name' => 'Member aggregate navigation ' . $scope,
        ]], $context);
        $this->repository('channel')->create([[
            'id' => $channelId,
            'name' => 'Member aggregate channel ' . $scope,
            'accessKey' => 'member-aggregate-' . $scope . '-' . \bin2hex(\random_bytes(4)),
            'typeId' => $default->getTypeId(),
            'languageId' => $default->getLanguageId(),
            'countryId' => $default->getCountryId(),
            'memberGroupId' => $groupId,
            'navigationCategoryId' => $categoryId,
            'navigationCategoryVersionId' => $default->getNavigationCategoryVersionId(),
            'languages' => [['id' => $default->getLanguageId()]],
            'countries' => [['id' => $default->getCountryId()]],
        ]], $context);
        $this->repository('tag')->create([[
            'id' => $tagId,
            'name' => 'Member aggregate tag ' . $scope,
        ]], $context);
        $this->repository('member')->create([[
            'id' => $memberId,
            'groupId' => $groupId,
            'channelId' => $channelId,
            'languageId' => $default->getLanguageId(),
            'memberNumber' => 'member-' . $scope . '-' . \bin2hex(\random_bytes(4)),
            'name' => 'Member aggregate ' . $scope,
            'email' => 'member-' . $scope . '-' . \bin2hex(\random_bytes(4)) . '@example.invalid',
            'addresses' => [[
                'id' => $addressId,
                'countryId' => $default->getCountryId(),
                'firstName' => 'Member',
                'lastName' => 'Aggregate',
                'city' => 'Test city',
                'street' => 'Test street 1',
            ]],
        ]], $context);
        $this->repository('member_recovery')->create([[
            'id' => $recoveryId,
            'memberId' => $memberId,
            'hash' => 'member-recovery-' . $recoveryId,
        ]], $context);
        $this->repository('member_tag')->create([[
            'memberId' => $memberId,
            'tagId' => $tagId,
        ]], $context);
        $this->repository('member_group_registration_channel')->create([[
            'memberGroupId' => $groupId,
            'channelId' => $channelId,
        ]], $context);

        return [
            'group' => $groupId,
            'channel' => $channelId,
            'member' => $memberId,
            'address' => $addressId,
            'recovery' => $recoveryId,
            'tag' => $tagId,
        ];
    }

    /**
     * @param list<string> $ids
     */
    private function assertMappingCount(string $entityName, string $property, array $ids, int $expected, Context $context, string $scope): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter($property, $ids));

        static::assertCount(
            $expected,
            $this->repository($entityName)->searchIds($criteria, $context)->getIds(),
            'Unexpected ' . $entityName . ' rows for ' . $scope,
        );
    }

    private function assertStoredTenant(string $table, string $idColumn, string $id, ?string $expectedTenantId): void
    {
        $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
            \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `%s` = :id', $table, $idColumn),
            ['id' => Uuid::fromHexToBytes($id)],
        );

        static::assertSame($expectedTenantId, $tenantId === false ? null : $tenantId);
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
